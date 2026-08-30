<?php

declare(strict_types=1);

namespace App\Logging\Admin;

use App\Logging\LogDomain;

it('defaults to no filters and health hidden', function (): void {
    $filters = new LogRowFilters;

    expect($filters->domain)->toBeNull()
        ->and($filters->level)->toBeNull()
        ->and($filters->hideHealth)->toBeTrue();
});

it('clones every field but level when asked without it', function (): void {
    $filters = new LogRowFilters(
        domain: LogDomain::Shop,
        level: 'warn',
        phase: 'did',
        event: 'order.place',
        requestId: 'req_1',
        txnId: 'txn_1',
        sessionId: 'ses_1',
        actorId: 'cus_1',
        msg: 'placed',
        from: '2026-08-24T00:00:00Z',
        to: '2026-08-25T00:00:00Z',
        key: 'data.order_id',
        value: 'ord_1',
        hideHealth: false,
    );

    $withoutLevel = $filters->withoutLevel();

    expect($withoutLevel->level)->toBeNull()
        ->and($withoutLevel->domain)->toBe(LogDomain::Shop)
        ->and($withoutLevel->phase)->toBe('did')
        ->and($withoutLevel->event)->toBe('order.place')
        ->and($withoutLevel->requestId)->toBe('req_1')
        ->and($withoutLevel->txnId)->toBe('txn_1')
        ->and($withoutLevel->sessionId)->toBe('ses_1')
        ->and($withoutLevel->actorId)->toBe('cus_1')
        ->and($withoutLevel->msg)->toBe('placed')
        ->and($withoutLevel->from)->toBe('2026-08-24T00:00:00Z')
        ->and($withoutLevel->to)->toBe('2026-08-25T00:00:00Z')
        ->and($withoutLevel->key)->toBe('data.order_id')
        ->and($withoutLevel->value)->toBe('ord_1')
        ->and($withoutLevel->hideHealth)->toBeFalse();
});
