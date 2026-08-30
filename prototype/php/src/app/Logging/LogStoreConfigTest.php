<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Support\Env;
use InvalidArgumentException;

/**
 * config/log_store.php runs `LogRetentionDays::parse()` over
 * `LOG_RETENTION_DAYS` while it loads, and that file loads on every
 * boot — before a request is ever routed, the way every other config file
 * does. These exercise that file directly rather than the parser it calls,
 * which `LogRetentionDaysTest` already covers on its own.
 *
 * `LOG_DATABASE_FILE` is out of scope here: `phpunit.xml` sets it at the
 * real process environment (so the rest of the suite never writes a
 * store), and once a variable arrives that way, `Env::getRepository()`'s
 * `set()`/`clear()` cannot shadow it for the rest of this process — unlike
 * a variable `.env` alone supplies, which is all `LOG_RETENTION_DAYS` ever
 * is here. `App\Logging\LogStoreServiceProviderTest` covers the
 * `database_file` value being read and wired through via `config()`
 * overrides instead; its own `storage_path('logs.sqlite3')` default is
 * only exercised live, outside this suite.
 */

/** @var string|null $shipped */
$shipped = null;

beforeEach(function () use (&$shipped): void {
    $repository = Env::getRepository();
    $shipped = $repository->get('LOG_RETENTION_DAYS');
    $repository->clear('LOG_RETENTION_DAYS');
});

afterEach(function () use (&$shipped): void {
    $repository = Env::getRepository();

    if ($shipped === null) {
        $repository->clear('LOG_RETENTION_DAYS');
    } else {
        $repository->set('LOG_RETENTION_DAYS', $shipped);
    }
});

it('refuses to boot when LOG_RETENTION_DAYS is malformed', function (): void {
    Env::getRepository()->set('LOG_RETENTION_DAYS', 'forever');

    expect(fn () => require config_path('log_store.php'))
        ->toThrow(InvalidArgumentException::class, 'LOG_RETENTION_DAYS must be a positive integer or "off"');
});

it('defaults retention to 14 days when LOG_RETENTION_DAYS is unset', function (): void {
    /** @var array{retention_days: ?int} $config */
    $config = require config_path('log_store.php');

    expect($config['retention_days'])->toBe(14);
});

it('disables retention for "off"', function (): void {
    Env::getRepository()->set('LOG_RETENTION_DAYS', 'off');

    /** @var array{retention_days: ?int} $config */
    $config = require config_path('log_store.php');

    expect($config['retention_days'])->toBeNull();
});

it('reads a configured retention window', function (): void {
    Env::getRepository()->set('LOG_RETENTION_DAYS', '30');

    /** @var array{retention_days: ?int} $config */
    $config = require config_path('log_store.php');

    expect($config['retention_days'])->toBe(30);
});
