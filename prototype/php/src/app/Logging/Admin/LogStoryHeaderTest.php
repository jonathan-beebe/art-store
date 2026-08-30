<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * @param  array<string, mixed>  $overrides
 */
function storyRow(array $overrides = []): LogRow
{
    return LogRow::fromDatabase(array_replace([
        'id' => 1,
        'ts' => '2026-08-24T12:00:00.000Z',
        'level' => 'info',
        'event' => null,
        'phase' => null,
        'msg' => null,
        'request_id' => 'req_1',
        'session_id' => null,
        'actor_type' => null,
        'actor_id' => null,
        'txn_id' => null,
        'duration_ms' => null,
        'data' => null,
        'error' => null,
    ], $overrides));
}

it('is empty for no lines', function (): void {
    $header = LogStoryHeader::of([]);

    expect($header->firstTs)->toBeNull()
        ->and($header->lastTs)->toBeNull()
        ->and($header->durationMs)->toBeNull()
        ->and($header->sessionId)->toBeNull()
        ->and($header->actorType)->toBeNull()
        ->and($header->actorId)->toBeNull();
});

it('reads the span, the root close duration, and the first session and actor', function (): void {
    $lines = [
        storyRow(['id' => 1, 'ts' => '2026-08-24T12:00:00.000Z', 'event' => 'http.request', 'phase' => 'will']),
        storyRow(['id' => 2, 'ts' => '2026-08-24T12:00:00.005Z', 'session_id' => 'ses_1', 'actor_type' => 'customer', 'actor_id' => 'cus_1']),
        storyRow(['id' => 3, 'ts' => '2026-08-24T12:00:00.010Z', 'session_id' => 'ses_2', 'actor_type' => 'admin', 'actor_id' => 'adm_1']),
        storyRow(['id' => 4, 'ts' => '2026-08-24T12:00:00.020Z', 'event' => 'http.request', 'phase' => 'did', 'duration_ms' => 20]),
    ];

    $header = LogStoryHeader::of($lines);

    expect($header->firstTs)->toBe('2026-08-24T12:00:00.000Z')
        ->and($header->lastTs)->toBe('2026-08-24T12:00:00.020Z')
        ->and($header->durationMs)->toBe(20)
        ->and($header->sessionId)->toBe('ses_1')
        ->and($header->actorType)->toBe('customer')
        ->and($header->actorId)->toBe('cus_1');
});

it('reads the root close duration off a failed close the same as a did close', function (): void {
    $lines = [
        storyRow(['id' => 1, 'ts' => 't1', 'event' => 'http.request', 'phase' => 'will']),
        storyRow(['id' => 2, 'ts' => 't2', 'event' => 'http.request', 'phase' => 'failed', 'duration_ms' => 9]),
    ];

    expect(LogStoryHeader::of($lines)->durationMs)->toBe(9);
});

it('has no duration when the request never closed within the capped lines', function (): void {
    $lines = [storyRow(['id' => 1, 'ts' => 't1', 'event' => 'http.request', 'phase' => 'will'])];

    expect(LogStoryHeader::of($lines)->durationMs)->toBeNull();
});
