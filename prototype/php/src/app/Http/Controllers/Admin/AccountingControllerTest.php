<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Fulfillment\RefundFulfillment;
use App\Models\Admin;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('reconciles every seller: held, available, paid out, and refunded', function (): void {
    $admin = Admin::factory()->create();
    $this->deliveredFulfillmentFor($this->seller('Blue Kiln Studio'), priceCents: 10000);
    $refunded = $this->deliveredFulfillmentFor($this->seller('Rye Press'), priceCents: 5000);
    app(RefundFulfillment::class)($refunded, $admin, 'Arrived damaged.', $this->moment('2026-08-23 09:00:00'));

    $response = $this->actingAs($admin, 'admin')->get('/admin/accounting');

    $response->assertOk();
    expect($response->getContent())
        ->toMatch('/data-cell="paid-out"[\s\S]*?\$0\.00/')
        ->toMatch('/data-cell="refunded"[\s\S]*?\$45\.00/');
});

it('lists a seller with no ledger activity at all, reconciling at zero', function (): void {
    $seller = $this->seller('Quiet Press');

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/accounting');

    $response->assertOk();
    $response->assertSee('Quiet Press');
    expect($response->getContent())->toMatch('/data-seller="'.$seller->id.'"[\s\S]*?\$0\.00[\s\S]*?\$0\.00/');
});

it('shows the platform totals: held, available, paid out, refunded, fees earned, fees refunded', function (): void {
    $admin = Admin::factory()->create();
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);
    $refunded = $this->deliveredFulfillmentFor($this->seller(), priceCents: 5000);
    app(RefundFulfillment::class)($refunded, $admin, 'Arrived damaged.', $this->moment('2026-08-23 09:00:00'));

    $response = $this->actingAs($admin, 'admin')->get('/admin/accounting');

    $response->assertOk();
    expect($response->getContent())
        ->toMatch('/data-totals="platform"[\s\S]*data-cell="held"[\s\S]*?\$0\.00/')
        ->toMatch('/data-cell="available"[\s\S]*?\$90\.00/')
        ->toMatch('/data-cell="fees-earned"[\s\S]*?\$10\.00/')
        ->toMatch('/data-cell="fees-refunded"[\s\S]*?\$5\.00/');
});

it('folds the balance for every seller out of one read of the ledger, whatever the seller count', function (): void {
    $this->deliveredFulfillmentFor($this->seller('Blue Kiln Studio'), priceCents: 10000);
    $this->shippedFulfillmentFor($this->seller('Rye Press'), priceCents: 20000);
    $this->seller('Quiet Press');

    $ledgerReads = 0;
    DB::listen(function (QueryExecuted $query) use (&$ledgerReads): void {
        $ledgerReads += str_contains($query->sql, 'ledger_entries') ? 1 : 0;
    });

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/accounting');

    $response->assertOk();
    expect($ledgerReads)->toBe(1);
});

it('sends a guest to the admin login page', function (): void {
    $this->get('/admin/accounting')->assertRedirect(route('auth.admin.login'));
});
