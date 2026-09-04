<?php

declare(strict_types=1);

namespace App\Logging;

use InvalidArgumentException;

/**
 * config/log_store.php runs `LogRetentionDays::parse()` over
 * `LOG_RETENTION_DAYS` while it loads, and that file loads on every
 * boot — before a request is ever routed, the way every other config file
 * does. These exercise that file directly rather than the parser it calls,
 * which `LogRetentionDaysTest` already covers on its own.
 *
 * These write `$_ENV`/`$_SERVER`/`putenv()` directly rather than through
 * `Illuminate\Support\Env::getRepository()`: that repository is immutable
 * once a process has ever seen a value for a key (`.env` supplies
 * `LOG_RETENTION_DAYS` on every boot), so a second `set()` for the same key
 * silently no-ops under Pest's `--parallel` worker, which boots the
 * application once before running a file's tests rather than once per
 * process the way a serial run does. `env()` still reads through that same
 * repository, and its reader chain checks `$_SERVER`, then `$_ENV`, then
 * `putenv()`, so a case that only wrote one of the three would still read
 * back a stale value out of the others.
 *
 * `LOG_DATABASE_FILE` is out of scope here: `phpunit.xml` sets it at the
 * real process environment, so no override here could shadow it anyway.
 * `App\Logging\LogStoreServiceProviderTest` covers the `database_file`
 * value being read and wired through via `config()` overrides instead; its
 * own `storage_path('logs.sqlite3')` default is only exercised live,
 * outside this suite.
 */
function setRetentionDaysEnv(?string $value): void
{
    if ($value === null) {
        putenv('LOG_RETENTION_DAYS');
        unset($_ENV['LOG_RETENTION_DAYS'], $_SERVER['LOG_RETENTION_DAYS']);

        return;
    }

    putenv("LOG_RETENTION_DAYS={$value}");
    $_ENV['LOG_RETENTION_DAYS'] = $value;
    $_SERVER['LOG_RETENTION_DAYS'] = $value;
}

/** @var string|null $shipped */
$shipped = null;

beforeEach(function () use (&$shipped): void {
    $shipped = getenv('LOG_RETENTION_DAYS') ?: null;
    setRetentionDaysEnv(null);
});

afterEach(function () use (&$shipped): void {
    setRetentionDaysEnv($shipped);
});

it('refuses to boot when LOG_RETENTION_DAYS is malformed', function (): void {
    setRetentionDaysEnv('forever');

    expect(fn () => require config_path('log_store.php'))
        ->toThrow(InvalidArgumentException::class, 'LOG_RETENTION_DAYS must be a positive integer or "off"');
});

it('defaults retention to 14 days when LOG_RETENTION_DAYS is unset', function (): void {
    /** @var array{retention_days: ?int} $config */
    $config = require config_path('log_store.php');

    expect($config['retention_days'])->toBe(14);
});

it('disables retention for "off"', function (): void {
    setRetentionDaysEnv('off');

    /** @var array{retention_days: ?int} $config */
    $config = require config_path('log_store.php');

    expect($config['retention_days'])->toBeNull();
});

it('reads a configured retention window', function (): void {
    setRetentionDaysEnv('30');

    /** @var array{retention_days: ?int} $config */
    $config = require config_path('log_store.php');

    expect($config['retention_days'])->toBe(30);
});
