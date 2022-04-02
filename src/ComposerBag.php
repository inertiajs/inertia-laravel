<?php

namespace Inertia;

use Illuminate\Support\Arr;

class ComposerBag
{
    /** @var ResponseFactory */
    protected $factory;

    /** @var array */
    protected $composers = [];

    /**
     * @param  ResponseFactory  $factory
     */
    public function __construct(ResponseFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @param  string  $component
     * @param  Closure|mixed  $composer
     */
    public function set($component, $composer)
    {
        $this->composers[$component][] = $composer;

        return $this;
    }

    /**
     * @param  string|null  $component
     */
    public function get($component = null)
    {
        return is_null($component)
            ? $this->composers
            : Arr::get($this->composers, $component);
    }

    /**
     * @param  string  $component
     */
    public function compose($component)
    {
        $composers = $this->get($component);

        foreach ($composers ?? [] as $composer) {
            if (is_string($composer)) {
                app($composer)->compose($this->factory);
            } else {
                $composer($this->factory);
            }
        }
    }
}
