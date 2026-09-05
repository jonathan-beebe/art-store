<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\LogStore;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the process's one `App\Logging\LogStore` handle: opened lazily, the
 * first time something resolves it — in practice `App\Logging\LogStoreTap`,
 * the first time a line logs — against `config('log_store.database_file')`.
 * One handle per process is what lets the ingest path
 * (`App\Logging\LogStoreTap`) and the retention prune
 * (`App\Console\Commands\SweepOrders`) share one connection.
 */
final class LogStoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            LogStore::class,
            fn (): LogStore => LogStore::open((string) config('log_store.database_file')),
        );
    }
}
