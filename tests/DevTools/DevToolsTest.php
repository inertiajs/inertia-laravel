<?php

namespace Inertia\Tests\DevTools;

use Illuminate\Http\Request;
use Inertia\DevTools\DevTools;
use Inertia\Tests\TestCase;

class DevToolsTest extends TestCase
{
    public function test_explicit_config_takes_precedence_over_the_environment(): void
    {
        config()->set('inertia.devtools.enabled', true);
        $this->app['env'] = 'production';
        $this->assertTrue(DevTools::enabled());

        config()->set('inertia.devtools.enabled', false);
        $this->app['env'] = 'local';
        $this->assertFalse(DevTools::enabled());
    }

    public function test_defaults_to_the_local_environment_when_unconfigured(): void
    {
        config()->set('inertia.devtools.enabled', null);

        $this->app['env'] = 'local';
        $this->assertTrue(DevTools::enabled());

        $this->app['env'] = 'production';
        $this->assertFalse(DevTools::enabled());
    }

    public function test_no_recorder_is_resolved_for_an_excluded_path(): void
    {
        config()->set('inertia.devtools.enabled', true);
        config()->set('inertia.devtools.except', ['health']);

        // Without the request, recording is on; with it, an excluded path skips the whole
        // lifecycle rather than collecting sources and props only to drop the entry later.
        $this->assertNotNull(DevTools::recorder());
        $this->assertNotNull(DevTools::recorder(Request::create('/dashboard')));
        $this->assertNull(DevTools::recorder(Request::create('/health')));
    }

    public function test_no_recorder_is_resolved_when_devtools_is_disabled(): void
    {
        config()->set('inertia.devtools.enabled', false);

        $this->assertNull(DevTools::recorder());
        $this->assertNull(DevTools::recorder(Request::create('/dashboard')));
    }

    public function test_string_except_patterns_are_honoured(): void
    {
        config()->set('inertia.devtools.enabled', true);
        config()->set('inertia.devtools.except', ['admin/*', 42, null]);

        $this->assertFalse(DevTools::enabledForRequest(Request::create('/admin/users')));
        $this->assertTrue(DevTools::enabledForRequest(Request::create('/dashboard')));
    }
}
