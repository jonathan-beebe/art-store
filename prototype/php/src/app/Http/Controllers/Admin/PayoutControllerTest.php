<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Payout;

it('lists every payout across every seller', function (): void {
    $blueKiln = $this->seller('Blue Kiln Studio');
    $ryePress = $this->seller('Rye Press');
    Payout::create(['seller_id' => $blueKiln->id, 'period_start' => '2026-08-10', 'period_end' => '2026-08-16', 'amount_cents' => 9000, 'paid_at' => '2026-08-17 00:00:00']);
    Payout::create(['seller_id' => $ryePress->id, 'period_start' => '2026-08-10', 'period_end' => '2026-08-16', 'amount_cents' => 5000, 'paid_at' => '2026-08-17 00:00:00']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/payouts');

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Rye Press');
});

it('narrows the list to one seller', function (): void {
    $blueKiln = $this->seller('Blue Kiln Studio');
    $ryePress = $this->seller('Rye Press');
    Payout::create(['seller_id' => $blueKiln->id, 'period_start' => '2026-08-10', 'period_end' => '2026-08-16', 'amount_cents' => 9000, 'paid_at' => '2026-08-17 00:00:00']);
    Payout::create(['seller_id' => $ryePress->id, 'period_start' => '2026-08-10', 'period_end' => '2026-08-16', 'amount_cents' => 5000, 'paid_at' => '2026-08-17 00:00:00']);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/payouts?seller={$blueKiln->id}");

    // Every seller still names itself in the filter's own select, so the
    // table row is what proves the filter — the amount only Rye Press paid.
    $response->assertSee('$90.00');
    $response->assertDontSee('$50.00');
});

it('sends a guest to the admin login page', function (): void {
    $response = $this->get('/admin/payouts');

    $response->assertRedirect(route('auth.admin.login'));
});
