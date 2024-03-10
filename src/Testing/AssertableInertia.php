<?php

namespace Inertia\Testing;

use InvalidArgumentException;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert as PHPUnit;
use PHPUnit\Framework\AssertionFailedError;
use Illuminate\Testing\Fluent\AssertableJson;

class AssertableInertia extends AssertableJson
{
    /** @var string */
    private $component;

    /** @var string */
    private $url;

    /** @var string|null */
    private $version;

    /** @var string */
    private $bundleConfig;

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
            PHPUnit::assertArrayHasKey('bundleConfig', $page);
        } catch (AssertionFailedError $e) {
            PHPUnit::fail('Not a valid Inertia response.');
        }

        $instance = static::fromArray($page['props']);
        $instance->component = $page['component'];
        $instance->url = $page['url'];
        $instance->version = $page['version'];
        $instance->bundleConfig = $page['bundleConfig'];

        return $instance;
    }

    public function component(string $value = null, $shouldExist = null): self
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

    public function bundleConfig(string $value): self
    {
        PHPUnit::assertSame($value, $this->bundleConfig, 'Unexpected Inertia bundle config.');

        return $this;
    }

    public function toArray()
    {
        return [
            'component' => $this->component,
            'props' => $this->prop(),
            'url' => $this->url,
            'version' => $this->version,
            'bundleConfig' => $this->bundleConfig,
        ];
    }
}
