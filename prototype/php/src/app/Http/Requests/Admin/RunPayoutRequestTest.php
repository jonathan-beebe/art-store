<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use DateTimeImmutable;

it('refuses a submission with an unparseable date', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')
        ->post('/admin/payouts', ['as_of' => 'yesterdayish']);

    $response->assertSessionHasErrors('as_of');
});

it('reads the submitted date, or falls back to the default when blank', function (): void {
    $default = new DateTimeImmutable('2026-08-24 09:00:00');

    $withDate = RunPayoutRequest::create('/admin/payouts', 'POST', ['as_of' => '2026-08-17']);
    $blank = RunPayoutRequest::create('/admin/payouts', 'POST', ['as_of' => '']);

    expect($withDate->asOf($default)->format('Y-m-d'))->toBe('2026-08-17')
        ->and($blank->asOf($default))->toBe($default);
});
