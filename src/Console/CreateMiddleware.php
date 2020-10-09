<?php

namespace Inertia\Console;

use Illuminate\Console\Command;
use Throwable;
use View;

class CreateMiddleware extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inertia:middleware {name=Inertia : Name of the Middleware that should be created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a new Inertia middleware';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        View::addNamespace('inertia', __DIR__.'/../../stubs');
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws Throwable
     */
    public function handle()
    {
        file_put_contents(
            app_path('Http/Middleware/'.$this->argument('name').'.php'),
            view('inertia::Middleware', ['name' => $this->argument('name')])->render()
        );

        $this->info('Inertia middleware ['.$this->argument('name').'] created successfully.');
    }
}
