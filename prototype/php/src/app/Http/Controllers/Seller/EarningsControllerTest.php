<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Fulfillment;
use App\Models\Payout;
use App\Models\Seller;
use Tests\CommerceTestCase;

final class EarningsControllerTest extends CommerceTestCase
{
    public function test_it_sends_a_signed_out_visitor_to_the_sign_in_page(): void
    {
        $this->get('/seller/earnings')->assertRedirect(route('auth.seller.login'));
    }

    public function test_it_renders_the_earnings_page(): void
    {
        $response = $this->actingAs($this->seller(), 'seller')->get('/seller/earnings');

        $response->assertOk();
        $response->assertSee('Earnings');
        $response->assertSee('Run weekly payout now');
    }

    public function test_it_reports_the_subtotal_fee_and_net_of_each_sale(): void
    {
        $seller = $this->seller();
        $this->paidFulfillment($seller, 10000);

        $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

        $response->assertSee('$100.00');
        $response->assertSee('$10.00');
        $response->assertSee('$90.00');
    }

    public function test_it_keeps_another_sellers_sales_off_the_report(): void
    {
        $this->paidFulfillment($this->seller('Other Studio'), 10000, 'Not Mine');

        $response = $this->actingAs($this->seller(), 'seller')->get('/seller/earnings');

        $response->assertDontSee('Not Mine');
    }

    public function test_it_holds_a_paid_sale_in_escrow(): void
    {
        $seller = $this->seller();
        $this->paidFulfillment($seller, 10000);

        $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

        $response->assertViewHas('balance', fn ($balance): bool => $balance->held->cents === 9000 && $balance->available->cents === 0);
    }

    public function test_it_moves_a_delivered_sale_to_available(): void
    {
        $seller = $this->seller();
        $fulfillment = $this->paidFulfillment($seller, 10000);
        app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM1', $this->moment('2026-08-21 10:00:00'));
        app(ConfirmDelivered::class)($fulfillment->fresh(), $this->moment('2026-08-22 10:00:00'));

        $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

        $response->assertViewHas('balance', fn ($balance): bool => $balance->available->cents === 9000);
    }

    public function test_it_lists_the_payouts_of_this_seller_only(): void
    {
        $seller = $this->seller();
        Payout::create([
            'seller_id' => $seller->id,
            'period_start' => '2026-08-10',
            'period_end' => '2026-08-16',
            'amount_cents' => 9000,
            'paid_at' => '2026-08-17 00:00:00',
        ]);
        Payout::create([
            'seller_id' => $this->seller('Other Studio')->id,
            'period_start' => '2026-08-10',
            'period_end' => '2026-08-16',
            'amount_cents' => 4200,
            'paid_at' => '2026-08-17 00:00:00',
        ]);

        $response = $this->actingAs($seller, 'seller')->get('/seller/earnings');

        $response->assertViewHas('payouts', fn ($payouts): bool => $payouts->count() === 1);
        $response->assertSee('Aug 10, 2026');
        $response->assertDontSee('$42.00');
    }

    private function paidFulfillment(Seller $seller, int $priceCents, string $title = 'Harbour at Dusk'): Fulfillment
    {
        $order = $this->orderFor(
            $this->verifiedCustomer(),
            $this->listing($seller, ['price_cents' => $priceCents, 'title' => $title]),
        );
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

        return Fulfillment::where('seller_id', $seller->id)->sole();
    }
}
