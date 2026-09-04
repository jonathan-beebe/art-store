<?php

declare(strict_types=1);

namespace App\Logging\Admin;

it('links an id back into the log list as the matching filter', function (): void {
    expect(LogFilterLinks::href('request', 'req_1'))->toBe(route('admin.logs.index', ['request' => 'req_1']))
        ->and(LogFilterLinks::href('txn', 'txn_1'))->toBe(route('admin.logs.index', ['txn' => 'txn_1']))
        ->and(LogFilterLinks::href('session', 'ses_1'))->toBe(route('admin.logs.index', ['session' => 'ses_1']))
        ->and(LogFilterLinks::href('actor', 'cus_1'))->toBe(route('admin.logs.index', ['actor' => 'cus_1']));
});

it('carries the caller\'s other current filters through', function (): void {
    $href = LogFilterLinks::href('actor', 'cus_1', ['domain' => 'shop', 'level' => 'warn']);

    expect($href)->toBe(route('admin.logs.index', ['domain' => 'shop', 'level' => 'warn', 'actor' => 'cus_1']));
});

it('overrides the same param already present in the current filters', function (): void {
    $href = LogFilterLinks::href('session', 'ses_2', ['session' => 'ses_1', 'msg' => 'checkout']);

    expect($href)->toBe(route('admin.logs.index', ['session' => 'ses_2', 'msg' => 'checkout']));
});

it('never carries a page number — the link always lands on page 1', function (): void {
    $href = LogFilterLinks::href('request', 'req_1', ['page' => '3', 'level' => 'warn']);

    expect($href)->not->toContain('page=3');
});
