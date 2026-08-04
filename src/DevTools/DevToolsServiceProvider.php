<?php

namespace Inertia\DevTools;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\DevTools\Http\Authorize;
use Inertia\DevTools\Http\EntriesController;
use Inertia\DevTools\Http\PreserveFlashData;
use Inertia\DevTools\Http\PreventPreviousUrlTracking;

class DevToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EntryStore::class, fn () => new EntryStore);

        $this->app->scoped(SourceLocator::class, fn () => new SourceLocator);

        $this->app->singleton(EntriesRepository::class, function () {
            $path = config('inertia.devtools.storage.path');

            return new EntriesRepository(
                path: is_string($path) ? $path : storage_path('inertia-devtools'),
                autoPruneHours: config()->integer('inertia.devtools.storage.ttl', 24),
            );
        });

        // Scoped: the recorder holds per-request collection state across the lifecycle
        // callbacks. It self-disables (every method no-ops) when devtools is off.
        $this->app->scoped(RequestRecorder::class, fn () => new RequestRecorder);

        // Only requests are recorded, so the entry is flushed once the request has been handled.
        // Everything is resolved from the current container because Octane serves each request
        // from a clone of the application, and that clone is where the entry was recorded.
        $this->app['events']->listen(RequestHandled::class, function () {
            if (! DevTools::enabled()) {
                return;
            }

            $repository = app(EntriesRepository::class);

            app(EntryStore::class)->flush($repository);

            $repository->pruneIfDue();
        });
    }

    public function boot(): void
    {
        if (! DevTools::enabled()) {
            return;
        }

        $middleware = [
            PreventPreviousUrlTracking::class,
            ...$this->routeMiddleware(),
            PreserveFlashData::class,
            Authorize::class,
        ];

        Route::middleware($middleware)
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
