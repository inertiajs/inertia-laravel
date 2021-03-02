<?php

namespace Inertia\Testing;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Traits\Macroable;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\AssertionFailedError;

class Assert implements Arrayable
{
    use Concerns\Has,
        Concerns\Matching,
        Concerns\Debugging,
        Concerns\PageObject,
        Concerns\Interaction,
        Macroable;

    /** @var string */
    private $component;

    /** @var array */
    private $props;

    /** @var string */
    private $url;

    /** @var mixed|null */
    private $version;

    /** @var string */
    private $path;

    protected function __construct(string $component, array $props, string $url, $version = null, string $path = null)
    {
        $this->path = $path;

        $this->component = $component;
        $this->props = $props;
        $this->url = $url;
        $this->version = $version;
    }

    protected function dotPath($key): string
    {
        if (is_null($this->path)) {
            return $key;
        }

        return implode('.', [$this->path, $key]);
    }

    protected function scope($key, Closure $callback): self
    {
        $props = $this->prop($key);
        $path = $this->dotPath($key);

        PHPUnit::assertIsArray($props, sprintf('Inertia property [%s] is not scopeable.', $path));

        $scope = new self($this->component, $props, $this->url, $this->version, $path);
        $callback($scope);
        $scope->interacted();

        return $this;
    }

    public static function fromTestResponse($response): self
    {
        try {
            $response->assertViewHas('page');
            $page = json_decode(json_encode($response->viewData('page')), true);

            PHPUnit::assertIsArray($page);
            PHPUnit::assertArrayHasKey('component', $page);
            PHPUnit::assertArrayHasKey('props', $page);
            PHPUnit::assertArrayHasKey('url', $page);
            PHPUnit::assertArrayHasKey('version', $page);
        } catch (AssertionFailedError $e) {
            PHPUnit::fail('Not a valid Inertia response.');
        }

        return new self($page['component'], $page['props'], $page['url'], $page['version']);
    }
}
