<?php

namespace Inertia\Testing\Facades;

use Illuminate\Support\Facades\Facade;
use Inertia\Testing\Manager;

/**
 * @method static Manager setComponentNameResolver(callable $resolver)
 * @method static string resolveComponentName(string $value)
 * @method static string findComponent(string $value)
 *
 * @see \Inertia\Testing\Manager
 */
class InertiaTesting extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Manager::class;
    }
}
