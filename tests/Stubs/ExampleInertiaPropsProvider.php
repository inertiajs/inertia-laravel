<?php

namespace Inertia\Tests\Stubs;

use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;

class ExampleInertiaPropsProvider implements ProvidesInertiaProperties
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        protected array $properties
    ) {}

    /**
     * @return iterable<string, mixed>
     */
    public function toInertiaProperties(RenderContext $context): iterable
    {
        return $this->properties;
    }
}
