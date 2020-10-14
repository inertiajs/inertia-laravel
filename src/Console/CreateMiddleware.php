<?php

namespace Inertia\Console;

use Illuminate\Console\GeneratorCommand;

class CreateMiddleware extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inertia:middleware {name=HandleInertiaRequests : Name of the Middleware that should be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Inertia middleware';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Middleware';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/../../stubs/middleware.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Http\Middleware';
    }
}
