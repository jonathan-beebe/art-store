<?php

namespace App\Http\Controllers\Seller;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Fulfillment;
use App\Models\Payout;
use App\Models\Seller;
use Illuminate\Support\Carbon;
use Tests\CommerceTestCase;

final class PayoutControllerTest extends CommerceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-24 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_sends_a_signed_out_visitor_to_the_sign_in_page(): void
    {
        $this->post('/seller/earnings/payouts')->assertRedirect(route('auth.seller.login'));
    }

    public function test_it_pays_out_the_released_escrow_of_the_last_completed_week(): void
    {
        $seller = $this->seller();
        $this->deliveredFulfillment($seller);

        $response = $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

        $response->assertRedirect(route('seller.earnings'));
        $payout = Payout::where('seller_id', $seller->id)->sole();
        $this->assertSame(9000, $payout->amount_cents);
    }

    public function test_it_flashes_the_count_and_the_amount(): void
    {
        $seller = $this->seller();
        $this->deliveredFulfillment($seller);

        $response = $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

        $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, '1 payout(s)')
            && str_contains($status, '$90.00'));
    }

    public function test_a_run_with_nothing_released_pays_nobody(): void
    {
        $seller = $this->seller();

        $response = $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

        $response->assertSessionHas('status', fn (string $status): bool => str_contains($status, '0 payout(s)')
            && str_contains($status, '$0.00'));
        $this->assertSame(0, Payout::count());
    }

    public function test_a_second_run_of_the_same_week_pays_nothing_again(): void
    {
        $seller = $this->seller();
        $this->deliveredFulfillment($seller);
        $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

        $this->actingAs($seller, 'seller')->post('/seller/earnings/payouts');

        $this->assertSame(1, Payout::count());
    }

    private function deliveredFulfillment(Seller $seller): Fulfillment
    {
        $order = $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 10000]));
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-17 10:00:00'));
        $fulfillment = Fulfillment::where('seller_id', $seller->id)->sole();
        app(MarkShipped::class)($fulfillment, 'Royal Mail', 'RM1', $this->moment('2026-08-18 10:00:00'));
        app(ConfirmDelivered::class)($fulfillment->fresh(), $this->moment('2026-08-19 10:00:00'));

        return $fulfillment->fresh();
    }
}
