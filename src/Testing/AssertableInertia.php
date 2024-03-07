<?php

namespace Inertia\Testing;

use InvalidArgumentException;
use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Testing\Fakes\Fake;
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
        } catch (AssertionFailedError $e) {
            PHPUnit::fail('Not a valid Inertia response.');
        }

        $instance = static::fromArray($page['props']);
        $instance->component = $page['component'];
        $instance->url = $page['url'];
        $instance->version = $page['version'];

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

    public function requestProp(string $prop, Closure $assert = null): self
    {
        $request = app('inertia.testing.request');
        if ($request === null) {
            if (Event::getFacadeRoot() instanceof Fake) {
                PHPUnit::fail('Unable to listen to the `\Illuminate\Foundation\Http\Events\RequestHandled` event. Please use Event::fakeExcept(RequestHandled::class) when using requestProp if you want to block other events.');
            }

            PHPUnit::fail('Unable to catch the previous request via the `\Illuminate\Foundation\Http\Events\RequestHandled` event using listener.');
        }

        $request->headers->add([
            'X-Inertia-Partial-Component' => $this->component,
            'X-Inertia-Partial-Data' => $prop,
        ]);


        $kernel = app()->make(Kernel::class);
        $response = $kernel->handle($request);

        $kernel->terminate($request, $response);

        $testResponse = TestResponse::fromBaseResponse($response);
        $testResponse->assertInertia(function (AssertableInertia $page) use ($prop, $assert) {
            $page->has($prop);

            if($assert !== null){
                $assert($page);
            }
        });

        return $this;
    }

    public function toArray()
    {
        return [
            'component' => $this->component,
            'props' => $this->prop(),
            'url' => $this->url,
            'version' => $this->version,
        ];
    }
}
