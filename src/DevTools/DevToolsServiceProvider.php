<?php

namespace Inertia\DevTools;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\DevTools\Http\Authorize;
use Inertia\DevTools\Http\EntriesController;
use Inertia\DevTools\Http\PreventPreviousUrlTracking;

class DevToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EntryStore::class, fn () => new EntryStore);

        $this->app->scoped(SourceLocator::class, fn () => new SourceLocator);

        $this->app->singleton(EntriesRepository::class, function ($app) {
            $path = $app['config']->get('inertia.devtools.storage.path');

            return new EntriesRepository(
                path: is_string($path) ? $path : storage_path('inertia-devtools'),
                autoPruneHours: $app['config']->integer('inertia.devtools.storage.ttl', 24),
            );
        });

        // Scoped: the recorder holds per-request collection state across the lifecycle
        // callbacks. It self-disables (every method no-ops) when devtools is off.
        $this->app->scoped(RequestRecorder::class, fn () => new RequestRecorder);

        $this->app->terminating(function () {
            if (! DevTools::enabled()) {
                return;
            }

            $repository = $this->app->make(EntriesRepository::class);

            $this->app->make(EntryStore::class)->flush($repository);

            $repository->pruneIfDue();
        });
    }

    public function boot(): void
    {
        if (! DevTools::enabled()) {
            return;
        }

        // Authorize is appended last so it always runs, after any middleware the
        // configured stack needs to resolve the user.
        Route::middleware([PreventPreviousUrlTracking::class, ...$this->routeMiddleware(), Authorize::class])
            ->prefix('_inertia/devtools')
            ->group(function () {
                Route::get('entries', [EntriesController::class, 'index']);
                Route::get('entries/{id}', [EntriesController::class, 'show']);
            });
    }

    /**
     * The middleware the entry endpoints run before they are authorized. Defaults to the
     * `web` group so the gate may authorize the user from the session it starts.
     *
     * @return array<int, mixed>
     */
    protected function routeMiddleware(): array
    {
        return Arr::wrap($this->app['config']->get('inertia.devtools.middleware', ['web']));
    }
}
