<?php

namespace Inertia;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response as BaseResponse;
use Illuminate\Support\Traits\Macroable;

class ResponseFactory
{
    use Macroable;

    /** @var string */
    protected $rootView = 'app';

    /** @var array */
    protected $sharedProps = [];

    /** @var Closure|string|null */
    protected $version;

    public function setRootView(string $name): void
    {
        $this->rootView = $name;
    }

    /**
     * @param  string|array|Arrayable  $key
     * @param  mixed|null  $value
     */
    public function share($key, $value = null): void
    {
        if (is_array($key)) {
            $this->sharedProps = array_merge($this->sharedProps, $key);
        } elseif ($key instanceof Arrayable) {
            $this->sharedProps = array_merge($this->sharedProps, $key->toArray());
        } else {
            Arr::set($this->sharedProps, $key, $value);
        }
    }

    /**
     * @param  string|null  $key
     * @param  null|mixed  $default
     * @return mixed
     */
    public function getShared(string $key = null, $default = null)
    {
        if ($key) {
            return Arr::get($this->sharedProps, $key, $default);
        }

        return $this->sharedProps;
    }

    public function flushShared(): void
    {
        $this->sharedProps = [];
    }

    /**
     * @param  Closure|string|null  $version
     */
    public function version($version): void
    {
        $this->version = $version;
    }

    public function getVersion(): string
    {
        $version = $this->version instanceof Closure
            ? App::call($this->version)
            : $this->version;

        return (string) $version;
    }

    public function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * @param  string  $component
     * @param  array|Arrayable  $props
     * @return Response
     */
    public function render(string $component, $props = []): Response
    {
        if ($props instanceof Arrayable) {
            $props = $props->toArray();
        }

        return new Response(
            $component,
            array_merge($this->sharedProps, $props),
            $this->rootView,
            $this->getVersion()
        );
    }

    /**
     * @param  string|RedirectResponse  $url
     */
    public function location($url): \Symfony\Component\HttpFoundation\Response
    {
        if ($url instanceof RedirectResponse) {
            $url = $url->getTargetUrl();
        }

        if (Request::inertia()) {
            return BaseResponse::make('', 409, ['X-Inertia-Location' => $url]);
        }

        return new RedirectResponse($url);
    }

    /**
     * @param  array|Arrayable  $rules
     * @return void
     */
    public function validate($rules = []): void
    {
        if (Request::hasHeader('X-Inertia-Validate')) {
            // We extract the attribute names from the $rules
            $rules_attribute_names = array_filter(array_keys($rules), fn ($key) => is_string($key));
            // We take the input data that the client wants to be validated from the 'X-Inertia-Validate' header
            $inertia_validate = explode(',', Request::header('X-Inertia-Validate'));
            // We remove any input data that is not in the $rules and the Request::all()
            $inertia_validate = array_intersect($inertia_validate, $rules_attribute_names, collect(Request::all())->keys()->toArray());
            if (! empty($inertia_validate) && ! empty($rules)) {
                $validator = validator(
                    Request::only($inertia_validate),
                    collect($rules)->map(function ($rule, $attribute) {
                        if (is_string($rule)) {
                            $rule = explode('|', $rule);
                        }
                        if (! in_array('sometimes', $rule)) {
                            array_unshift($rule, 'sometimes');
                        }
                        return $rule;
                    })->toArray()
                );
                $validator->validate();
            }
            // We throw this exception to stop the controller from continuing
            // Since at this point, we know the request has a 'X-Inertia-Validate' header
            // And because we're doing real-time validation, we don't want the controller to continue
            throw \Illuminate\Validation\ValidationException::withMessages([]);
        }
    }
}
