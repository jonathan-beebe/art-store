<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Models\Payout;
use App\Models\Seller;
use Tests\CommerceTestCase;

final class RunWeeklyPayoutsTest extends CommerceTestCase
{
    public function test_it_pays_the_week_that_ended_before_the_given_date(): void
    {
        $seller = $this->seller();
        $this->deliverASale($seller);

        $this->artisan('payouts:run', ['--as-of' => '2026-08-24'])
            ->expectsOutputToContain('2026-08-17 to 2026-08-23')
            ->expectsOutputToContain('Blue Kiln Studio $405.00')
            ->expectsOutputToContain('1 seller(s) paid.')
            ->assertSuccessful();

        $this->assertSame(40500, Payout::query()->sole()->amount_cents);
    }

    public function test_it_reports_a_week_with_nothing_to_pay(): void
    {
        $this->artisan('payouts:run', ['--as-of' => '2026-08-24'])
            ->expectsOutputToContain('No seller has a released balance')
            ->assertSuccessful();
    }

    private function deliverASale(Seller $seller): void
    {
        $order = app(FinalizeOrder::class)(
            $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 45000])),
            '4242 4242 4242 4242',
            $this->moment('2026-08-20 10:00:00'),
        );
        $fulfillment = app(MarkShipped::class)(
            $order->fulfillments()->sole(),
            'USPS',
            '9400111899',
            $this->moment('2026-08-20 11:00:00'),
        );
        app(ConfirmDelivered::class)($fulfillment, $this->moment('2026-08-21 11:00:00'));
    }
}
