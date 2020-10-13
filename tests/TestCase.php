<?php

namespace Inertia\Tests;

use Illuminate\Support\Facades\View;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return ['Inertia\ServiceProvider'];
    }

    public function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__.'/stubs');
    }
}
