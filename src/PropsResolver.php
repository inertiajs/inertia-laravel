<?php

namespace Inertia;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Inertia\Support\Header;

class PropsResolver
{
    use ResolvesCallables;

    /**
     * The current request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * The component being rendered.
     *
     * @var string
     */
    protected $component;

    /**
     * Whether this is a partial request for the given component.
     *
     * @var bool
     */
    protected $isPartial;

    /**
     * Whether this is an Inertia request.
     *
     * @var bool
     */
    protected $isInertia;

    /**
     * The props to include in the partial response.
     *
     * @var array<int, string>|null
     */
    protected $only;

    /**
     * The props to exclude from the partial response.
     *
     * @var array<int, string>|null
     */
    protected $except;

    /**
     * The props that should have their merge state reset.
     *
     * @var array<int, string>
     */
    protected $resetProps;

    /**
     * The once-props that the client has already loaded.
     *
     * @var array<int, string>
     */
    protected $loadedOnceProps;

    /**
     * The deferred props grouped by their defer group.
     *
     * @var array<string, array<int, string>>
     */
    protected $deferredProps = [];

    /**
     * The props that should be appended to existing client-side data.
     *
     * @var array<int, string>
     */
    protected $mergeProps = [];

    /**
     * The props that should be prepended to existing client-side data.
     *
     * @var array<int, string>
     */
    protected $prependProps = [];

    /**
     * The props that should be deep merged with existing client-side data.
     *
     * @var array<int, string>
     */
    protected $deepMergeProps = [];

    /**
     * The key matching strategies for mergeable props.
     *
     * @var array<int, string>
     */
    protected $matchPropsOn = [];

    /**
     * The scroll pagination metadata for each scroll prop.
     *
     * @var array<string, array<string, mixed>>
     */
    protected $scrollProps = [];

    /**
     * The once-prop metadata for each once prop.
     *
     * @var array<string, array<string, mixed>>
     */
    protected $onceProps = [];

    /**
     * The top-level keys of shared props.
     *
     * @var array<int, string>
     */
    protected $sharedPropKeys = [];

    /**
     * Create a new props resolver instance.
     */
    public function __construct(Request $request, string $component)
    {
        $this->request = $request;
        $this->component = $component;

        $this->isPartial = $request->header(Header::PARTIAL_COMPONENT) === $component;
        $this->isInertia = (bool) $request->header(Header::INERTIA);
        $this->only = $this->parseHeader(Header::PARTIAL_ONLY);
        $this->except = $this->parseHeader(Header::PARTIAL_EXCEPT);
        $this->resetProps = $this->parseHeader(Header::RESET) ?? [];
        $this->loadedOnceProps = $this->parseHeader(Header::EXCEPT_ONCE_PROPS) ?? [];
    }

    /**
     * Resolve the given shared and page props, collecting their metadata.
     *
     * @param  array<array-key, mixed|ProvidesInertiaProperties>  $shared
     * @param  array<array-key, mixed|ProvidesInertiaProperties>  $props
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    public function resolve(array $shared, array $props): array
    {
        $props = array_merge($this->resolveSharedProps($shared), $props);

        return [
            $this->resolveProps($this->unpackDotProps($props)),
            $this->buildMetadata(),
        ];
    }

    /**
     * Resolve shared property providers and collect shared prop keys.
     *
     * @param  array<array-key, mixed|ProvidesInertiaProperties>  $shared
     * @return array<string, mixed>
     */
    protected function resolveSharedProps(array $shared): array
    {
        $resolved = $this->resolvePropertyProviders($shared);

        if (! config('inertia.expose_shared_prop_keys', true)) {
            return $resolved;
        }

        foreach (array_keys($resolved) as $key) {
            $this->sharedPropKeys[] = str_contains((string) $key, '.')
                ? strstr((string) $key, '.', true)
                : (string) $key;
        }

        $this->sharedPropKeys = array_values(array_unique($this->sharedPropKeys));

        return $resolved;
    }

    /**
     * Resolve ProvidesInertiaProperties instances into keyed props.
     *
     * @param  array<array-key, mixed>  $props
     * @return array<string, mixed>
     */
    protected function resolvePropertyProviders(array $props): array
    {
        $context = null;
        $result = [];

        foreach ($props as $key => $value) {
            if (is_numeric($key) && $value instanceof ProvidesInertiaProperties) {
                $context ??= new RenderContext($this->component, $this->request);

                /** @var array<string, mixed> $provided */
                $provided = collect($value->toInertiaProperties($context))->all();
                $result = array_merge($result, $provided);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Build the non-empty metadata arrays for the page response.
     *
     * @return array<string, mixed>
     */
    protected function buildMetadata(): array
    {
        return array_filter([
            'sharedProps' => $this->sharedPropKeys,
            'mergeProps' => $this->mergeProps,
            'prependProps' => $this->prependProps,
            'deepMergeProps' => $this->deepMergeProps,
            'matchPropsOn' => $this->matchPropsOn,
            'deferredProps' => $this->deferredProps,
            'scrollProps' => $this->scrollProps,
            'onceProps' => $this->onceProps,
        ], fn ($value) => count($value) > 0);
    }

    /**
     * Recursively resolve the props tree, collecting metadata along the way.
     *
     * @param  array<array-key, mixed>  $props
     * @return array<string, mixed>
     */
    protected function resolveProps(array $props, string $prefix = '', bool $parentWasResolved = false): array
    {
        $props = $this->resolvePropertyProviders($props);
        $result = [];

        foreach ($props as $key => $value) {
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";
            $prop = $value;

            // On partial requests, we only include props that match the paths
            // specified in the request headers. AlwaysProp instances and the
            // children of already-resolved values bypass this filter.
            if (! $this->shouldIncludeInPartialResponse($prop, $path, $parentWasResolved)) {
                continue;
            }

            // On initial page loads, certain prop types (e.g. DeferProp,
            // OptionalProp) are excluded before resolution to avoid
            // executing their closures unnecessarily.
            if (! $this->isPartial && $this->excludeFromInitialResponse($prop, $path)) {
                continue;
            }

            $value = $this->resolveValue($prop, $path, $props);

            // A closure may return a prop type instead of a plain value. When
            // this happens, we unwrap it one more level so the prop type can
            // participate in filtering and metadata collection below.
            if ($value !== $prop && $this->isPropType($value)) {
                $prop = $value;

                // Check again after unwrapping: the resolved prop type may
                // itself need to be excluded from the initial response.
                if (! $this->isPartial && $this->excludeFromInitialResponse($prop, $path)) {
                    continue;
                }

                $value = $this->resolveValue($prop, $path, $props);
            }

            $this->collectMetadata($prop, $path);

            // When the resolved value is an array, we recurse into it. If the
            // original prop was not already an array (e.g. a closure that
            // returned one), its children bypass partial filtering.
            $result[$key] = is_array($value)
                ? $this->resolveProps($value, $path, $parentWasResolved || ! is_array($prop))
                : $value;
        }

        return $result;
    }

    /**
     * Determine if a prop should be included in a partial response. AlwaysProp
     * and children of already-resolved values bypass partial filtering.
     */
    protected function shouldIncludeInPartialResponse(mixed $prop, string $path, bool $parentWasResolved): bool
    {
        if (! $this->isPartial || $prop instanceof AlwaysProp || $parentWasResolved) {
            return true;
        }

        return $this->pathMatchesPartialRequest($path);
    }

    /**
     * Determine if the given path matches the current partial request using
     * bidirectional prefix matching on the only/except headers.
     */
    protected function pathMatchesPartialRequest(string $path): bool
    {
        if ($this->only !== null && ! $this->matchesOnly($path) && ! $this->leadsToOnly($path)) {
            return false;
        }

        if ($this->except !== null && $this->matchesExcept($path)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a prop should be excluded from the initial page response.
     * Each exclusion type collects its metadata before the prop is removed.
     */
    protected function excludeFromInitialResponse(mixed $prop, string $path): bool
    {
        // OptionalProp and DeferProp implement IgnoreFirstLoad and are never
        // sent on the initial page load. They still contribute deferred,
        // merge, and once metadata for the client to act on.
        if ($prop instanceof IgnoreFirstLoad) {
            return $this->excludeIgnoredProp($prop, $path);
        }

        // ScrollProp and other Deferrable types may be configured to defer
        // their initial load. They contribute deferred-group and merge
        // metadata so the client knows to request them separately.
        if ($prop instanceof Deferrable && $prop->shouldDefer()) {
            return $this->excludeDeferredProp($prop, $path);
        }

        // Once-props that the client has already loaded are excluded on
        // subsequent Inertia visits to avoid sending duplicate data.
        // The client tracks loaded once-props via the except-once header.
        if ($this->isInertia && $this->wasAlreadyLoadedByClient($prop, $path)) {
            return $this->excludeAlreadyLoadedProp($prop, $path);
        }

        return false;
    }

    /**
     * Exclude an IgnoreFirstLoad prop from the initial response while
     * collecting its deferred, merge, and once metadata.
     */
    protected function excludeIgnoredProp(mixed $prop, string $path): bool
    {
        if ($prop instanceof Deferrable && $prop->shouldDefer()
            && ! $this->wasAlreadyLoadedByClient($prop, $path)) {
            $this->collectDeferredPropMetadata($path, $prop);
        }

        if ($prop instanceof Mergeable && $prop->shouldMerge()) {
            $this->collectMergeableMetadata($path, $prop);
        }

        if ($prop instanceof Onceable && $prop->shouldResolveOnce()) {
            $this->collectOnceMetadata($path, $prop);
        }

        return true;
    }

    /**
     * Exclude a deferred prop from the initial response while
     * collecting its deferred-group and merge metadata.
     */
    protected function excludeDeferredProp(Deferrable $prop, string $path): bool
    {
        $this->collectDeferredPropMetadata($path, $prop);

        if ($prop instanceof Mergeable && $prop->shouldMerge()) {
            $this->collectMergeableMetadata($path, $prop);
        }

        return true;
    }

    /**
     * Exclude a once-prop that the client has already loaded while
     * preserving its once metadata for the client.
     */
    protected function excludeAlreadyLoadedProp(mixed $prop, string $path): bool
    {
        $this->collectOnceMetadata($path, $prop);

        return true;
    }

    /**
     * Determine if a once-prop has already been loaded by the client.
     */
    protected function wasAlreadyLoadedByClient(mixed $prop, string $path): bool
    {
        return $prop instanceof Onceable
            && $prop->shouldResolveOnce()
            && ! $prop->shouldBeRefreshed()
            && in_array($prop->getKey() ?? $path, $this->loadedOnceProps);
    }

    /**
     * Resolve a single prop value through the resolution pipeline.
     *
     * @param  array<string, mixed>  $siblings
     */
    protected function resolveValue(mixed $value, string $path, array $siblings): mixed
    {
        if ($value instanceof ScrollProp) {
            $value->configureMergeIntent($this->request);
        }

        $value = $this->resolveCallable($value);

        if ($value instanceof ProvidesInertiaProperty) {
            $value = $value->toInertiaProperty(new PropertyContext($path, $siblings, $this->request));
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if ($value instanceof PromiseInterface) {
            $value = $value->wait();
        }

        if ($value instanceof Responsable) {
            $response = $value->toResponse($this->request);

            if (method_exists($response, 'getData')) {
                $value = $response->getData(true);
            }
        }

        return $value;
    }

    /**
     * Determine if the value is a prop type that requires
     * further filtering or metadata collection.
     */
    protected function isPropType(mixed $value): bool
    {
        return $value instanceof AlwaysProp
            || $value instanceof Deferrable
            || $value instanceof IgnoreFirstLoad
            || $value instanceof Mergeable
            || $value instanceof Onceable;
    }

    /**
     * Collect metadata for a prop that will be included in the response.
     */
    protected function collectMetadata(mixed $prop, string $path): void
    {
        if ($prop instanceof Mergeable && $prop->shouldMerge()) {
            $this->collectMergeableMetadata($path, $prop);
        }

        if ($prop instanceof ScrollProp) {
            $this->collectScrollMetadata($path, $prop);
        }

        if ($prop instanceof Onceable && $prop->shouldResolveOnce()) {
            $this->collectOnceMetadata($path, $prop);
        }
    }

    /**
     * Collect the deferred prop and its group.
     */
    protected function collectDeferredPropMetadata(string $path, Deferrable $prop): void
    {
        $this->deferredProps[$prop->group()][] = $path;
    }

    /**
     * Collect the merge strategy for a mergeable prop.
     */
    protected function collectMergeableMetadata(string $path, Mergeable $prop): void
    {
        if (in_array($path, $this->resetProps)) {
            return;
        }

        if ($this->isPartial && ! $this->isIncludedInPartialMetadata($path)) {
            return;
        }

        if ($prop->shouldDeepMerge()) {
            $this->deepMergeProps[] = $path;
        } elseif ($prop->appendsAtRoot()) {
            $this->mergeProps[] = $path;
        } elseif ($prop->prependsAtRoot()) {
            $this->prependProps[] = $path;
        } else {
            foreach ($prop->appendsAtPaths() as $appendPath) {
                $this->mergeProps[] = "{$path}.{$appendPath}";
            }
            foreach ($prop->prependsAtPaths() as $prependPath) {
                $this->prependProps[] = "{$path}.{$prependPath}";
            }
        }

        foreach ($prop->matchesOn() as $strategy) {
            $this->matchPropsOn[] = "{$path}.{$strategy}";
        }
    }

    /**
     * Collect scroll pagination metadata.
     *
     * @param  ScrollProp<mixed>  $prop
     */
    protected function collectScrollMetadata(string $path, ScrollProp $prop): void
    {
        $this->scrollProps[$path] = [
            ...$prop->metadata(),
            'reset' => in_array($path, $this->resetProps),
        ];
    }

    /**
     * Collect once-prop metadata.
     */
    protected function collectOnceMetadata(string $path, mixed $prop): void
    {
        if (! $prop instanceof Onceable || ! $prop->shouldResolveOnce()) {
            return;
        }

        if ($this->isPartial && ! $this->isIncludedInPartialMetadata($path)) {
            return;
        }

        $this->onceProps[$prop->getKey() ?? $path] = [
            'prop' => $path,
            'expiresAt' => $prop->expiresAt(),
        ];
    }

    /**
     * Determine if the path should contribute metadata during a partial request.
     */
    protected function isIncludedInPartialMetadata(string $path): bool
    {
        if ($this->only !== null && ! $this->matchesOnly($path)) {
            return false;
        }

        if ($this->except !== null && $this->matchesExcept($path)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the path matches or is a descendant of an "only" filter path.
     */
    protected function matchesOnly(string $path): bool
    {
        foreach ($this->only as $onlyPath) {
            if ($path === $onlyPath || str_starts_with($path, "{$onlyPath}.")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the path is an ancestor of an "only" filter path.
     */
    protected function leadsToOnly(string $path): bool
    {
        foreach ($this->only as $onlyPath) {
            if (str_starts_with($onlyPath, "{$path}.")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the path matches or is a descendant of an "except" filter path.
     */
    protected function matchesExcept(string $path): bool
    {
        foreach ($this->except as $exceptPath) {
            if ($path === $exceptPath || str_starts_with($path, "{$exceptPath}.")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unpack top-level dot-notation keys into nested arrays.
     *
     * @param  array<array-key, mixed>  $props
     * @return array<array-key, mixed>
     */
    protected function unpackDotProps(array $props): array
    {
        foreach ($props as $key => $value) {
            if (! is_string($key) || ! str_contains($key, '.')) {
                continue;
            }

            if ($value instanceof Closure) {
                $value = App::call($value);
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            $this->ensurePathIsTraversable($props, $key);
            Arr::set($props, $key, $value);
            unset($props[$key]);
        }

        return $props;
    }

    /**
     * Resolve closures and Arrayable values along the intermediate segments
     * of a dot-notation path so that Arr::set can nest into them.
     *
     * @param  array<array-key, mixed>  $props
     */
    protected function ensurePathIsTraversable(array &$props, string $dotKey): void
    {
        $segments = explode('.', $dotKey);
        array_pop($segments);

        $current = &$props;

        foreach ($segments as $segment) {
            if (! isset($current[$segment])) {
                return;
            }

            if ($current[$segment] instanceof Closure) {
                $current[$segment] = App::call($current[$segment]);
            }

            if ($current[$segment] instanceof Arrayable) {
                $current[$segment] = $current[$segment]->toArray();
            }

            if (! is_array($current[$segment])) {
                return;
            }

            $current = &$current[$segment];
        }
    }

    /**
     * Parse a comma-separated header value into an array.
     *
     * @return array<int, string>|null
     */
    protected function parseHeader(string $key): ?array
    {
        return array_filter(explode(',', $this->request->header($key, ''))) ?: null;
    }
}
