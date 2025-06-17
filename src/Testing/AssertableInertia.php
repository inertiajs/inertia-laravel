<?php

namespace Inertia\Testing;

use Closure;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\AssertionFailedError;

class AssertableInertia extends AssertableJson
{
    /** @var string */
    private $component;

    /** @var string */
    private $url;

    /** @var string|null */
    private $version;

    /** @var bool */
    private $encryptHistory;

    /** @var bool */
    private $clearHistory;

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

        return $instance;
    }

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

    public function url(string $value): self
    {
        PHPUnit::assertSame($value, $this->url, 'Unexpected Inertia page url.');

        return $this;
    }

    public function version(string $value): self
    {
        PHPUnit::assertSame($value, $this->version, 'Unexpected Inertia asset version.');

        return $this;
    }

    /**
     * Reload the Inertia page and perform assertions on the response.
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
     */
    public function reloadOnly(array|string $only, ?Closure $callback = null): self
    {
        return $this->reload(only: $only, callback: function (AssertableInertia $assertable) use ($only, $callback) {
            $assertable->hasAll(explode(',', $only));

            if ($callback) {
                $callback($assertable);
            }
        });
    }

    /**
     * Reload the Inertia page as a partial request excluding the specified props.
     */
    public function reloadExcept(array|string $except, ?Closure $callback = null): self
    {
        return $this->reload(except: $except, callback: function (AssertableInertia $assertable) use ($except, $callback) {
            $assertable->missingAll(explode(',', $except));

            if ($callback) {
                $callback($assertable);
            }
        });
    }

    public function toArray()
    {
        return [
            'component' => $this->component,
            'props' => $this->prop(),
            'url' => $this->url,
            'version' => $this->version,
            'encryptHistory' => $this->encryptHistory,
            'clearHistory' => $this->clearHistory,
        ];
    }
}
