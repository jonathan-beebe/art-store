<?php

declare(strict_types=1);

namespace App\Providers;

use App\Analytics\Analytics;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the process's one `App\Analytics\Analytics` handle and flushes its
 * buffer once the response or command has already gone back.
 *
 * `Illuminate\Foundation\Application::handleRequest()` sends the response
 * before calling `$kernel->terminate()`, and `handleCommand()` calls
 * `$kernel->terminate()` after `$kernel->handle()` returns the same way.
 * Both kernels' `terminate()` end by calling `$this->app->terminate()`,
 * which runs every `terminating()` callback — the one mechanism the HTTP
 * and console kernels share. `Illuminate\Support\defer()` runs only through
 * `Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks`, which the
 * console kernel never adds to its stack, so a deferred callback recorded
 * from an artisan command never fires.
 *
 * The callback flushes only a store the request resolved — a request that
 * never called `recordEvent()`/`recordPageView()` never constructs one, so
 * most requests pay nothing here.
 */
final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Analytics::class, fn (): Analytics => new Analytics);
    }

    public function boot(): void
    {
        $this->app->terminating(function (): void {
            if ($this->app->resolved(Analytics::class)) {
                $this->app->make(Analytics::class)->flush();
            }
        });
    }
}
