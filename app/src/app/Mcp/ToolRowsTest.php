<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Logging\Admin\LogRow;
use DateTimeImmutable;
use DateTimeZone;

it('writes an instant as a UTC second', function (): void {
    $moment = new DateTimeImmutable('2026-09-05 10:30:00', new DateTimeZone('America/New_York'));

    expect(ToolRows::instant($moment))->toBe('2026-09-05T14:30:00Z');
});

it('decodes a stored line\'s data and error back into objects, keeping unparsable text as it was', function (): void {
    $row = LogRow::fromDatabase([
        'id' => 3,
        'ts' => '2026-09-05T14:30:00.000Z',
        'level' => 'error',
        'event' => 'order.pay',
        'phase' => 'failed',
        'msg' => 'the card was declined',
        'request_id' => 'req_1',
        'session_id' => null,
        'actor_type' => 'customer',
        'actor_id' => 'cus_1',
        'txn_id' => null,
        'duration_ms' => 12,
        'data' => '{"order_id":"ord_1"}',
        'error' => 'not json',
    ]);

    expect(ToolRows::logRow($row))->toBe([
        'id' => 3,
        'ts' => '2026-09-05T14:30:00.000Z',
        'level' => 'error',
        'event' => 'order.pay',
        'phase' => 'failed',
        'msg' => 'the card was declined',
        'request_id' => 'req_1',
        'session_id' => null,
        'actor_type' => 'customer',
        'actor_id' => 'cus_1',
        'txn_id' => null,
        'duration_ms' => 12,
        'data' => ['order_id' => 'ord_1'],
        'error' => 'not json',
    ]);
});
