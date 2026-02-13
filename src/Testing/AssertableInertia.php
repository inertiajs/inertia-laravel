<?php

namespace Inertia\Testing;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\AssertionFailedError;

class AssertableInertia extends AssertableJson
{
    /**
     * The Inertia component name for this page.
     *
     * @var string
     */
    private $component;

    /**
     * The current page URL.
     *
     * @var string
     */
    private $url;

    /**
     * The current asset version.
     *
     * @var string|null
     */
    private $version;

    /**
     * Whether history state should be encrypted.
     *
     * @var bool
     */
    private $encryptHistory;

    /**
     * Whether history should be cleared.
     *
     * @var bool
     */
    private $clearHistory;

    /**
     * The deferred props (if any).
     *
     * @var array<string, array<int, string>>
     */
    private $deferredProps;

    /**
     * The Flash Data (if any).
     *
     * @var array<string, mixed>
     */
    private $flash;

    /**
     * Create an AssertableInertia instance from a test response.
     *
     * @param  TestResponse<Response>  $response
     */
    public static function fromTestResponse(TestResponse $response): self
    {
        try {
            $response->assertViewHas('page');
            $page = json_decode(json_encode($response->viewData('page')), true);

            PHPUnit::assertIsArray($page);
            PHPUnit::assertArrayHasKey('component', $page);
            PHPUnit::assertArrayHasKey('props', $page);
            PHPUnit::assertArrayHasKey('url', $page);
            PHPUnit::assertArrayHasKey('version', $page);
            PHPUnit::assertArrayHasKey('encryptHistory', $page);
            PHPUnit::assertArrayHasKey('clearHistory', $page);
        } catch (AssertionFailedError $e) {
            PHPUnit::fail('Not a valid Inertia response.');
        }

        $instance = static::fromArray($page['props']);
        $instance->component = $page['component'];
        $instance->url = $page['url'];
        $instance->version = $page['version'];
        $instance->encryptHistory = $page['encryptHistory'];
        $instance->clearHistory = $page['clearHistory'];
        $instance->deferredProps = $page['deferredProps'] ?? [];
        $instance->flash = $page['flash'] ?? [];

        return $instance;
    }

    /**
     * Assert that the page uses the given component.
     *
     * @param  bool|null  $shouldExist
     */
    public function component(?string $value = null, $shouldExist = null): self
    {
        PHPUnit::assertSame($value, $this->component, 'Unexpected Inertia page component.');

        if ($shouldExist || (is_null($shouldExist) && config('inertia.testing.ensure_pages_exist', true))) {
            try {
                app('inertia.testing.view-finder')->find($value);
            } catch (InvalidArgumentException $exception) {
                PHPUnit::fail(sprintf('Inertia page component file [%s] does not exist.', $value));
            }
        }

        return $this;
    }

    /**
     * Assert that the current page URL matches the expected value.
     */
    public function url(string $value): self
    {
        PHPUnit::assertSame($value, $this->url, 'Unexpected Inertia page url.');

        return $this;
    }

    /**
     * Assert that the current asset version matches the expected value.
     */
    public function version(string $value): self
    {
        PHPUnit::assertSame($value, $this->version, 'Unexpected Inertia asset version.');

        return $this;
    }

    /**
     * Load the deferred props for the given groups and perform assertions on the response.
     *
     * @param  Closure|array<int, string>|string  $groupsOrCallback
     */
    public function loadDeferredProps(Closure|array|string $groupsOrCallback, ?Closure $callback = null): self
    {
        $callback = is_callable($groupsOrCallback) ? $groupsOrCallback : $callback;

        $groups = is_callable($groupsOrCallback) ? array_keys($this->deferredProps) : Arr::wrap($groupsOrCallback);

        $props = collect($groups)->flatMap(function ($group) {
            return $this->deferredProps[$group] ?? [];
        })->implode(',');

        return $this->reloadOnly($props, $callback);
    }

    /**
     * Reload the Inertia page and perform assertions on the response.
     *
     * @param  array<int, string>|string|null  $only
     * @param  array<int, string>|string|null  $except
     */
    public function reload(?Closure $callback = null, array|string|null $only = null, array|string|null $except = null): self
    {
        if (is_array($only)) {
            $only = implode(',', $only);
        }

        if (is_array($except)) {
            $except = implode(',', $except);
        }

        $reloadRequest = new ReloadRequest(
            $this->url,
            $this->component,
            $this->version,
            $only,
            $except,
        );

        $assertable = AssertableInertia::fromTestResponse($reloadRequest());

        // Make sure we get the same data as the original request.
        $assertable->component($this->component);
        $assertable->url($this->url);
        $assertable->version($this->version);

        if ($callback) {
            $callback($assertable);
        }

        return $this;
    }

    /**
     * Reload the Inertia page as a partial request with only the specified props.
     *
     * @param  array<int, string>|string  $only
     */
    public function reloadOnly(array|string $only, ?Closure $callback = null): self
    {
        return $this->reload(only: $only, callback: function (AssertableInertia $assertable) use ($only, $callback) {
            $props = is_array($only) ? $only : explode(',', $only);

            $assertable->hasAll($props);

            if ($callback) {
                $callback($assertable);
            }
        });
    }

    /**
     * Reload the Inertia page as a partial request excluding the specified props.
     *
     * @param  array<int, string>|string  $except
     */
    public function reloadExcept(array|string $except, ?Closure $callback = null): self
    {
        return $this->reload(except: $except, callback: function (AssertableInertia $assertable) use ($except, $callback) {
            $props = is_array($except) ? $except : explode(',', $except);

            $assertable->missingAll($props);

            if ($callback) {
                $callback($assertable);
            }
        });
    }

    /**
     * Assert that the Flash Data contains the given key, optionally with the expected value.
     */
    public function hasFlash(string $key, mixed $expected = null): self
    {
        func_num_args() > 1
            ? static::assertFlashHas($this->flash, $key, $expected)
            : static::assertFlashHas($this->flash, $key);

        return $this;
    }

    /**
     * Assert that the Flash Data does not contain the given key.
     */
    public function missingFlash(string $key): self
    {
        static::assertFlashMissing($this->flash, $key);

        return $this;
    }

    /**
     * Assert that the given flash data array contains the given key, optionally with the expected value.
     *
     * @param  array<string, mixed>  $flash
     */
    public static function assertFlashHas(array $flash, string $key, mixed $expected = null): void
    {
        PHPUnit::assertTrue(
            Arr::has($flash, $key),
            sprintf('Inertia Flash Data is missing key [%s].', $key)
        );

        if (func_num_args() > 2) {
            PHPUnit::assertSame(
                $expected,
                Arr::get($flash, $key),
                sprintf('Inertia Flash Data [%s] does not match expected value.', $key)
            );
        }
    }

    /**
     * Assert that the given flash data array does not contain the given key.
     *
     * @param  array<string, mixed>  $flash
     */
    public static function assertFlashMissing(array $flash, string $key): void
    {
        PHPUnit::assertFalse(
            Arr::has($flash, $key),
            sprintf('Inertia Flash Data has unexpected key [%s].', $key)
        );
    }

    /**
     * Convert the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return [
            'component' => $this->component,
            'props' => $this->prop(),
            'url' => $this->url,
            'version' => $this->version,
            'encryptHistory' => $this->encryptHistory,
            'clearHistory' => $this->clearHistory,
            'flash' => $this->flash,
        ];
    }
}
