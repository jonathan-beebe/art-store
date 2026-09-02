<?php

declare(strict_types=1);

namespace App\Analytics;

use RuntimeException;
use Tests\CapturedStory;

it('returns what the write returns when it succeeds', function (): void {
    $result = AnalyticsWriteGuard::attempt(fn (): string => 'ok');

    expect($result)->toBe('ok');
});

it('returns null and logs one warning naming the analytics database file when the write throws', function (): void {
    $log = CapturedStory::capture();

    $result = AnalyticsWriteGuard::attempt(function (): never {
        throw new RuntimeException('database is locked');
    });

    $line = $log->line('app.log', 'doing');

    expect($result)->toBeNull()
        ->and($line['level'])->toBe('warn')
        ->and($line['msg'])->toBe('⚠️ analytics write failed: database is locked')
        ->and($line['data'])->toBe(['analytics_database_file' => config('database.connections.analytics.database')]);
});
