<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Traits\Macroable;

class Response implements Responsable
{
    use Macroable;

    protected $component;
    protected $props;
    protected $rootView;
    protected $version;
    protected $viewData = [];
    protected $stackable;
    protected $basePageUrl;

    public function __construct($component, $props, $rootView = 'app', $version = null)
    {
        $this->component = $component;
        $this->props = $props instanceof Arrayable ? $props->toArray() : $props;
        $this->rootView = $rootView;
        $this->version = $version;
    }

    public function stackable()
    {
        $this->stackable = true;

        return $this;
    }

    public function basePageRoute(...$args)
    {
        $this->basePageUrl = URL::route(...$args);

        return $this;
    }

    public function basePageUrl($url)
    {
        $this->basePageUrl = $url;

        return $this;
    }

    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->props = array_merge($this->props, $key);
        } else {
            $this->props[$key] = $value;
        }

        return $this;
    }

    public function withViewData($key, $value = null)
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }

        return $this;
    }

    public function toResponse($request)
    {
        $only = array_filter(explode(',', $request->header('X-Inertia-Partial-Data')));

        $props = ($only && $request->header('X-Inertia-Partial-Component') === $this->component)
            ? Arr::only($this->props, $only)
            : array_filter($this->props, function ($prop) {
                return ! ($prop instanceof LazyProp);
            });

        array_walk_recursive($props, function (&$prop) use ($request) {
            if ($prop instanceof LazyProp) {
                $prop = App::call($prop);
            }

            if ($prop instanceof Closure) {
                $prop = App::call($prop);
            }

            if ($prop instanceof Responsable) {
                $prop = $prop->toResponse($request)->getData();
            }

            if ($prop instanceof Arrayable) {
                $prop = $prop->toArray();
            }
        });

        foreach ($props as $key => $value) {
            if (str_contains($key, '.')) {
                Arr::set($props, $key, $value);
                unset($props[$key]);
            }
        }

        if (! $request->header('X-Inertia-Stack') && $this->basePageUrl) {
            $kernel = App::make(Kernel::class);
            $url = $this->basePageUrl;

            do {
                $response = $kernel->handle(
                    $this->createBaseRequest($request, $url)
                );

                if (! $response->headers->get('X-Inertia') && ! $response->isRedirect()) {
                    return $response;
                }

                $url = $response->isRedirect() ? $response->getTargetUrl() : null;
            } while ($url);

            App::instance('request', $request);
            Facade::clearResolvedInstance('request');

            $page = $response->getData(true);
            $page['stacks'] = [
                [
                    'component' => $this->component,
                    'props' => $props,
                    'url' => $request->getRequestUri(),
                ]
            ];
        } else {
            $page = [
                'component' => $this->component,
                'props' => $props,
                'url' => $request->getRequestUri(),
                'version' => $this->version,
                'stackable' => $this->stackable,
                'stacks' => [],
            ];
        }

        if ($request->header('X-Inertia')) {
            return new JsonResponse($page, 200, [
                'Vary' => 'Accept',
                'X-Inertia' => 'true',
            ]);
        }

        return ResponseFactory::view($this->rootView, $this->viewData + ['page' => $page]);
    }

    public function createBaseRequest(Request $request, $url)
    {
        $headers = Arr::except($request->headers->all(), 'X-Inertia-Stack');
        $headers['Accept'] = 'text/html, application/xhtml+xml';
        $headers['X-Requested-With'] = 'XMLHttpRequest';
        $headers['X-Inertia'] = true;
        $headers['X-Inertia-Version'] = $this->version;

        $baseRequest = Request::create($url, 'GET');
        $baseRequest->headers->replace($headers);

        return $baseRequest;
    }
}
