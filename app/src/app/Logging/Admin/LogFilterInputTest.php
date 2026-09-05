<?php

declare(strict_types=1);

namespace App\Logging\Admin;

use App\Logging\LogDomain;
use Illuminate\Support\Facades\Validator;

it('builds filters from every field, reading a blank or absent field as no filter', function (): void {
    $filters = LogFilterInput::filters([
        'domain' => 'mcp',
        'level' => 'warn',
        'phase' => '',
        'event' => 'order.place',
        'request' => 'req_1',
        'txn' => 'txn_01J5X3M9A2K8YB7Q4R6T1V0WZE',
        'session' => null,
        'actor' => 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE',
        'msg' => 'placed',
        'from' => '2026-09-01T00:00:00Z',
        'to' => '2026-09-02T00:00:00Z',
        'key' => 'data.order_id',
        'value' => 'ord_1',
    ], hideHealth: false, hideViewer: true);

    expect($filters->domain)->toBe(LogDomain::Mcp)
        ->and($filters->level)->toBe('warn')
        ->and($filters->phase)->toBeNull()
        ->and($filters->event)->toBe('order.place')
        ->and($filters->requestId)->toBe('req_1')
        ->and($filters->txnId)->toBe('txn_01J5X3M9A2K8YB7Q4R6T1V0WZE')
        ->and($filters->sessionId)->toBeNull()
        ->and($filters->actorId)->toBe('cus_01J5X3M9A2K8YB7Q4R6T1V0WZE')
        ->and($filters->msg)->toBe('placed')
        ->and($filters->from)->toBe('2026-09-01T00:00:00Z')
        ->and($filters->to)->toBe('2026-09-02T00:00:00Z')
        ->and($filters->key)->toBe('data.order_id')
        ->and($filters->value)->toBe('ord_1')
        ->and($filters->hideHealth)->toBeFalse()
        ->and($filters->hideViewer)->toBeTrue();
});

it('validates every field with the viewer rules and refuses a value without a key', function (): void {
    $validator = Validator::make(LogFilterInput::blanked(['event' => '', 'value' => 'ord_1']), LogFilterInput::rules());
    $validator->after(LogFilterInput::requireKeyForValue(...));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('value'))->toBe(LogFilterInput::VALUE_NEEDS_KEY)
        ->and($validator->errors()->has('event'))->toBeFalse();

    $rejected = Validator::make(['domain' => 'storefront', 'actor' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE', 'txn' => ['txn_1']], LogFilterInput::rules());

    expect($rejected->errors()->keys())->toBe(['domain', 'txn', 'actor']);
});
