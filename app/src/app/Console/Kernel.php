<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Trims the base Kernel's command discovery to the half `bootstrap/app.php`
 * needs: `routes/console.php`'s schedule still loads, but the Finder scan of
 * `app/Console/Commands` does not. That scan reflects every `*.php` file
 * the directory holds, sidecar tests included, and under Pest's `--parallel`
 * worker — which boots the console kernel ahead of collecting tests — the
 * reflection probe autoloads a sidecar test before Pest ever requires it for
 * collection. The file's `it()` calls run once, outside Pest's context, and
 * register nothing; the command's sidecar test silently never runs.
 * `bootstrap/app.php`'s `withCommands()` call names the two real commands
 * explicitly, which needs no scan to find them.
 */
class Kernel extends ConsoleKernel
{
    protected function shouldDiscoverCommands(): bool
    {
        return true;
    }

    protected function discoverCommands(): void
    {
        /** @var array<string> $commandRoutePaths */
        $commandRoutePaths = $this->commandRoutePaths;

        foreach ($commandRoutePaths as $path) {
            if (file_exists($path)) {
                require $path;
            }
        }
    }
}
