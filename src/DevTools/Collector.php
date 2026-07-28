<?php

namespace Inertia\DevTools;

use Illuminate\Support\Arr;
use Inertia\DevTools\Data\PropType;

class Collector
{
    /** @var array<string, array<string, mixed>> */
    protected array $props = [];

    /** @var array{file: string, line: int}|null */
    protected ?array $renderSource = null;

    /** @var array<string, array{file: string, line: int}> */
    protected array $shareSources = [];

    /** @var array{name: string|null, uri: string, method: string}|null */
    protected ?array $route = null;

    /** @var array<int, string> */
    protected array $sharedKeys = [];

    protected ?string $routeAction = null;

    /** @var array{file: string, line: int}|null */
    protected ?array $actionSource = null;

    protected ?string $componentPath = null;

    /**
     * The resolved page props, kept nested so values may be plucked per prop path.
     *
     * @var array<string, mixed>
     */
    protected array $resolvedProps = [];

    public function __construct(protected string $component, protected SourceLocator $sourceLocator) {}

    public function setRenderSource(?string $file, ?int $line): void
    {
        if ($file !== null && $line !== null) {
            $this->renderSource = ['file' => $file, 'line' => $line];
        }
    }

    /**
     * @param  array<string, array{file: string, line: int}>  $sources
     */
    public function setShareSources(array $sources): void
    {
        $this->shareSources = $sources;
    }

    public function setRoute(?string $name, string $uri, string $method): void
    {
        $this->route = [
            'name' => $name,
            'uri' => $uri,
            'method' => $method,
        ];
    }

    /**
     * Set which top-level prop keys came from shared props.
     *
     * @param  array<int, string>  $keys
     */
    public function setSharedKeys(array $keys): void
    {
        $this->sharedKeys = $keys;
    }

    /**
     * Record a prop's Inertia wrapper type, defer group, and extended metadata.
     */
    public function addProp(
        string $path,
        ?PropType $inertiaType = null,
        ?string $deferGroup = null,
        bool $reset = false,
        bool $once = false,
        ?string $mergeDirection = null,
        bool $deepMerge = false,
        bool $rescued = false,
    ): void {
        $this->props[$path] = [
            'shared' => in_array($path, $this->sharedKeys, true),
            'inertiaType' => $inertiaType?->value,
        ];

        if ($deferGroup !== null) {
            $this->props[$path]['deferGroup'] = $deferGroup;
        }

        if (isset($this->shareSources[$path])) {
            $this->props[$path]['shareSource'] = $this->shareSources[$path];
        }

        if ($reset) {
            $this->props[$path]['reset'] = true;
        }

        if ($once) {
            $this->props[$path]['once'] = true;
        }

        if ($mergeDirection !== null) {
            $this->props[$path]['mergeDirection'] = $mergeDirection;
        }

        if ($deepMerge) {
            $this->props[$path]['deepMerge'] = true;
        }

        if ($rescued) {
            $this->props[$path]['rescued'] = true;
        }
    }

    /**
     * Store the route action and resolve its source location.
     */
    public function setRouteAction(?string $action, mixed $uses = null): void
    {
        if ($action === null) {
            return;
        }

        $this->routeAction = $action;

        $this->actionSource = $this->sourceLocator->resolveActionSource($action, $uses);
    }

    /**
     * Store the resolved file path of the frontend component.
     */
    public function setComponentPath(?string $path): void
    {
        if ($path !== null) {
            $this->componentPath = $path;
        }
    }

    /**
     * Store the resolved page props. Values are plucked per prop path when the entry
     * is built, so no work is spent flattening props that get pruned.
     *
     * @param  array<string, mixed>  $props
     */
    public function setResolvedProps(array $props): void
    {
        $this->resolvedProps = $props;
    }

    /**
     * Scan the render source file to find the line number of each non-shared prop key.
     */
    protected function resolveRenderPropLines(): void
    {
        if ($this->renderSource === null) {
            return;
        }

        $renderProps = collect($this->props)
            ->reject(fn (array $info) => $info['shared'] || isset($info['shareSource']))
            ->keys()
            ->all();

        if ($renderProps === []) {
            return;
        }

        foreach ($renderProps as $propKey) {
            $line = $this->sourceLocator->findPropKeyLine(
                $this->renderSource['file'],
                $this->renderSource['line'],
                $propKey,
            );

            if ($line !== null) {
                $this->props[$propKey]['renderSource'] = [
                    'file' => $this->renderSource['file'],
                    'line' => $line,
                ];
            }
        }
    }

    /**
     * Drop deep prop paths that carry no devtools metadata. Every top-level prop is
     * kept so nothing disappears from the tree; nested values are rendered from the
     * recorded prop values rather than one metadata row per leaf.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function pruneProps(): array
    {
        return collect($this->props)
            ->filter(fn (array $meta, string $path) => ! str_contains($path, '.') || $this->propHasMetadata($meta))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function propHasMetadata(array $meta): bool
    {
        return $meta['shared'] === true
            || $meta['inertiaType'] !== null
            || count($meta) > 2;
    }

    /**
     * Pluck the value backing each metadata node. Nested objects are stored once under
     * their prop path rather than duplicated as the parent object and every exploded
     * child path (e.g. `auth.user` alongside `auth.user.id`, `auth.user.name`).
     *
     * @param  array<int, string>  $paths
     * @return array<string, mixed>
     */
    protected function extractPropValues(array $paths): array
    {
        if ($this->resolvedProps === []) {
            return [];
        }

        $normalized = $this->normalizePropValues($this->resolvedProps);
        $values = [];

        foreach ($paths as $path) {
            if (Arr::has($normalized, $path)) {
                $values[$path] = Arr::get($normalized, $path);
            }
        }

        return $values;
    }

    /**
     * Cast the resolved props to plain arrays and scalars so recorded values match the
     * JSON the client received.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    protected function normalizePropValues(array $props): array
    {
        $encoded = json_encode($props);

        if (! is_string($encoded)) {
            return $props;
        }

        return json_decode($encoded, true);
    }

    /**
     * Assemble the final devtools metadata array.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $this->resolveRenderPropLines();

        $props = $this->pruneProps();

        $result = [
            'schemaVersion' => 1,
            'props' => $props,
            'component' => $this->component,
        ];

        if ($this->renderSource !== null) {
            $result['renderSource'] = $this->renderSource;
        }

        if ($this->route !== null) {
            $result['route'] = $this->route;
        }

        if ($this->routeAction !== null) {
            $result['route']['action'] = $this->routeAction;
        }

        if ($this->actionSource !== null) {
            $result['route']['actionSource'] = $this->actionSource;
        }

        if ($this->componentPath !== null) {
            $result['componentPath'] = $this->componentPath;
        }

        $propValues = $this->extractPropValues(array_keys($props));

        if ($propValues !== []) {
            $result['propValues'] = $propValues;
        }

        return $result;
    }
}
