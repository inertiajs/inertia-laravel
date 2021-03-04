<?php

namespace Inertia\Testing;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;
use PHPUnit\Framework\Assert as PHPUnit;

class Manager
{
    /**
     * The IoC container instance.
     *
     * @var ContainerContract
     */
    protected $container;

    /**
     * The page component name resolver instance.
     *
     * @var callable
     */
    protected $componentNameResolver;

    /**
     * Create a new Testing Manager instance.
     *
     * @param  ContainerContract|null  $container
     * @return void
     */
    public function __construct(ContainerContract $container = null)
    {
        $this->container = $container ?: new Container;
        $this->setComponentNameResolver(function ($page) {
            return $page;
        });
    }

    /**
     * Set the page component name resolver implementation.
     *
     * @param  callable  $resolver
     * @return $this
     */
    public function setComponentNameResolver(callable $resolver): Manager
    {
        $this->componentNameResolver = $resolver;

        return $this;
    }

    /**
     * Resolve the page component name.
     *
     * @param  string  $value
     * @return string
     */
    public function resolveComponentName(string $value): string
    {
        return call_user_func($this->componentNameResolver, $value);
    }

    /**
     * Find the page component on the filesystem.
     *
     * @param  string  $value
     * @return string
     */
    public function findComponent(string $value): string
    {
        $viewFinder = new FileViewFinder(
            $this->container['files'],
            $this->container['config']->get('inertia.testing.page_paths'),
            $this->container['config']->get('inertia.testing.page_extensions')
        );

        $componentName = $this->resolveComponentName($value);

        try {
            return $viewFinder->find($componentName);
        } catch (InvalidArgumentException $exception) {
            PHPUnit::fail(sprintf('Inertia page component file [%s] does not exist.', $value));
        }
    }
}
