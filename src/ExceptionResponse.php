<?php

namespace Inertia;

use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExceptionResponse implements Responsable
{
    protected ?string $component = null;

    /** @var array<string, mixed> */
    protected array $props = [];

    protected bool $includeSharedData = false;

    protected ?string $rootView = null;

    /** @var class-string<Middleware>|null */
    protected ?string $middlewareClass = null;

    public function __construct(
        public readonly Throwable $exception,
        public readonly Request $request,
        public readonly Response $response,
        protected readonly Router $router,
        protected readonly KernelContract $kernel,
    ) {}

    /**
     * @param  array<string, mixed>  $props
     */
    public function render(string $component, array $props = []): static
    {
        $this->component = $component;
        $this->props = $props;

        return $this;
    }

    /**
     * @param  class-string<Middleware>  $middlewareClass
     */
    public function usingMiddleware(string $middlewareClass): static
    {
        $this->middlewareClass = $middlewareClass;

        return $this;
    }

    public function withSharedData(): static
    {
        $this->includeSharedData = true;

        return $this;
    }

    public function rootView(string $rootView): static
    {
        $this->rootView = $rootView;

        return $this;
    }

    public function statusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($this->component === null) {
            return $this->response;
        }

        $middleware = $this->resolveMiddleware();

        if ($middleware) {
            Inertia::version(fn () => $middleware->version($this->request));
            Inertia::setRootView($this->rootView ?? $middleware->rootView($this->request));
        } elseif ($this->rootView) {
            Inertia::setRootView($this->rootView);
        }

        if ($this->includeSharedData && $middleware) {
            Inertia::share($middleware->share($this->request));

            foreach ($middleware->shareOnce($this->request) as $key => $value) {
                if ($value instanceof OnceProp) {
                    Inertia::share($key, $value);
                } else {
                    Inertia::shareOnce($key, $value);
                }
            }
        }

        return Inertia::render($this->component, $this->props)
            ->toResponse($this->request)
            ->setStatusCode($this->response->getStatusCode());
    }

    protected function resolveMiddleware(): ?Middleware
    {
        if ($this->middlewareClass) {
            return app($this->middlewareClass);
        }

        $class = $this->resolveMiddlewareFromRoute() ?? $this->resolveMiddlewareFromKernel();

        if ($class) {
            return app($class);
        }

        return null;
    }

    /**
     * @return class-string<Middleware>|null
     */
    protected function resolveMiddlewareFromRoute(): ?string
    {
        $route = $this->request->route();

        if (! $route) {
            return null;
        }

        foreach ($this->router->gatherRouteMiddleware($route) as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            $class = head(explode(':', $middleware));

            if (is_a($class, Middleware::class, true)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @return class-string<Middleware>|null
     */
    protected function resolveMiddlewareFromKernel(): ?string
    {
        foreach ($this->kernel->getMiddlewareGroups() as $group) {
            foreach ($group as $middleware) {
                if (is_string($middleware) && is_a($middleware, Middleware::class, true)) {
                    return $middleware;
                }
            }
        }

        return null;
    }
}
