<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\LogStore;
use Illuminate\Support\Facades\Log;
use Tests\LogStoreFixtures as Fixtures;

it('binds one LogStore per process, opened against the configured database file', function (): void {
    config(['log_store.database_file' => Fixtures::tempFile()]);

    $store = app(LogStore::class);

    expect($store)->toBeInstanceOf(LogStore::class)
        ->and($store->connection)->not->toBeNull()
        ->and(app(LogStore::class))->toBe($store);
});

it('never opens a file for the literal "off"', function (): void {
    config(['log_store.database_file' => 'off']);

    expect(app(LogStore::class)->connection)->toBeNull();
});

it('wires config(log_store.database_file) through to a working store, end to end via the stdout channel', function (): void {
    config(['log_store.database_file' => Fixtures::tempFile()]);
    $store = app(LogStore::class);

    Log::channel('stdout')->info('placing an order from the cart', ['event' => 'order.place', 'phase' => 'will']);
    $store->flush();

    expect(Fixtures::rowCount(Fixtures::connectionOrFail($store)))->toBe(1);
});
