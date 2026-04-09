<?php

namespace Inertia;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Inertia\Ssr\SsrState;
use Inertia\Support\Header;
use Inertia\Support\SessionKey;
use UnitEnum;

class Response implements Responsable
{
    use Macroable;

    /**
     * The name of the root component.
     *
     * @var string
     */
    protected $component;

    /**
     * The page props.
     *
     * @var array<string, mixed>
     */
    protected $props;

    /**
     * The name of the root view.
     *
     * @var string
     */
    protected $rootView;

    /**
     * The asset version.
     *
     * @var string
     */
    protected $version;

    /**
     * Indicates if the browser history should be cleared.
     *
     * @var bool
     */
    protected $clearHistory;

    /**
     * Indicates if the URL fragment should be preserved across redirects.
     *
     * @var bool
     */
    protected $preserveFragment;

    /**
     * Indicates if the browser history should be encrypted.
     *
     * @var bool
     */
    protected $encryptHistory;

    /**
     * The view data.
     *
     * @var array<string, mixed>
     */
    protected $viewData = [];

    /**
     * The URL resolver callback.
     */
    protected ?Closure $urlResolver = null;

    /**
     * The shared properties (before merge with page props).
     *
     * @var array<array-key, mixed|ProvidesInertiaProperties>
     */
    protected array $sharedProps = [];

    /**
     * Create a new Inertia response instance.
     *
     * @param  array<array-key, mixed|ProvidesInertiaProperties>  $sharedProps
     * @param  array<array-key, mixed|ProvidesInertiaProperties>  $props
     */
    public function __construct(
        string $component,
        array $sharedProps,
        array $props,
        string $rootView = 'app',
        string $version = '',
        bool $encryptHistory = false,
        ?Closure $urlResolver = null,
    ) {
        $this->component = $component;
        $this->sharedProps = $sharedProps;
        $this->props = $props;
        $this->rootView = $rootView;
        $this->version = $version;
        $this->clearHistory = session()->pull(SessionKey::CLEAR_HISTORY, false);
        $this->preserveFragment = session()->pull(SessionKey::PRESERVE_FRAGMENT, false);
        $this->encryptHistory = $encryptHistory;
        $this->urlResolver = $urlResolver;
    }

    /**
     * Add additional properties to the page.
     *
     * @param  string|array<string, mixed>|ProvidesInertiaProperties  $key
     * @param  mixed  $value
     * @return $this
     */
    public function with($key, $value = null): self
    {
        if ($key instanceof ProvidesInertiaProperties) {
            $this->props[] = $key;
        } elseif (is_array($key)) {
            $this->props = array_merge($this->props, $key);
        } else {
            $this->props[$key] = $value;
        }

        return $this;
    }

    /**
     * Add additional data to the view.
     *
     * @param  string|array<string, mixed>  $key
     * @param  mixed  $value
     * @return $this
     */
    public function withViewData($key, $value = null): self
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    /**
     * Set the root view.
     *
     * @return $this
     */
    public function rootView(string $rootView): self
    {
        $this->rootView = $rootView;

        return $this;
    }

    /**
     * Add flash data to the response.
     *
     * @param  BackedEnum|UnitEnum|string|array<string, mixed>  $key
     * @return $this
     */
    public function flash(BackedEnum|UnitEnum|string|array $key, mixed $value = null): self
    {
        Inertia::flash($key, $value);

        return $this;
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $resolver = new PropsResolver($request, $this->component);
        [$resolvedProps, $resolvedMetadata] = $resolver->resolve($this->sharedProps, $this->props);

        $page = array_merge(
            [
                'component' => $this->component,
                'props' => $resolvedProps,
                'url' => $this->getUrl($request),
                'version' => $this->version,
            ],
            $resolvedMetadata,
            $this->resolveClearHistory($request),
            $this->resolveEncryptHistory($request),
            $this->resolveFlashData($request),
            $this->resolvePreserveFragment($request),
        );

        if ($request->header(Header::INERTIA)) {
            return new JsonResponse($page, 200, [Header::INERTIA => 'true']);
        }

        App::make(SsrState::class)->setPage($page);

        return ResponseFactory::view($this->rootView, $this->viewData + ['page' => $page]);
    }

    /**
     * Resolve the clear history flag.
     *
     * @return array<string, mixed>
     */
    protected function resolveClearHistory(Request $request): array
    {
        return $this->clearHistory ? ['clearHistory' => true] : [];
    }

    /**
     * Resolve the encrypt history flag.
     *
     * @return array<string, mixed>
     */
    protected function resolveEncryptHistory(Request $request): array
    {
        return $this->encryptHistory ? ['encryptHistory' => true] : [];
    }

    /**
     * Resolve flash data from the session.
     *
     * @return array<string, mixed>
     */
    protected function resolveFlashData(Request $request): array
    {
        $flash = Inertia::pullFlashed($request);

        return $flash ? ['flash' => $flash] : [];
    }

    /**
     * Resolve the preserve fragment flag from the session.
     *
     * @return array<string, mixed>
     */
    protected function resolvePreserveFragment(Request $request): array
    {
        return $this->preserveFragment ? ['preserveFragment' => true] : [];
    }

    /**
     * Get the URL from the request while preserving the trailing slash.
     */
    protected function getUrl(Request $request): string
    {
        $urlResolver = $this->urlResolver ?? function (Request $request) {
            $url = Str::start(Str::after($request->fullUrl(), $request->getSchemeAndHttpHost()), '/');

            $rawUri = Str::before($request->getRequestUri(), '?');

            return Str::endsWith($rawUri, '/') ? $this->finishUrlWithTrailingSlash($url) : $url;
        };

        return App::call($urlResolver, ['request' => $request]);
    }

    /**
     * Ensure the URL has a trailing slash before the query string.
     */
    protected function finishUrlWithTrailingSlash(string $url): string
    {
        // Make sure the relative URL ends with a trailing slash and re-append the query string if it exists.
        $urlWithoutQueryWithTrailingSlash = Str::finish(Str::before($url, '?'), '/');

        return str_contains($url, '?')
            ? $urlWithoutQueryWithTrailingSlash.'?'.Str::after($url, '?')
            : $urlWithoutQueryWithTrailingSlash;
    }
}
