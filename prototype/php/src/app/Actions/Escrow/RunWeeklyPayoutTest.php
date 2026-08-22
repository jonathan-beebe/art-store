<?php

namespace App\Actions\Escrow;

use App\Actions\Fulfillment\ConfirmDelivered;
use App\Actions\Fulfillment\MarkShipped;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Escrow\LedgerEntryType;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Seller;
use Tests\CommerceTestCase;

final class RunWeeklyPayoutTest extends CommerceTestCase
{
    public function test_it_pays_a_seller_whose_delivery_landed_inside_the_period(): void
    {
        $seller = $this->seller();
        $this->deliverASale($seller, 45000, '2026-08-21 11:00:00');

        $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

        $this->assertCount(1, $payouts);
        $this->assertSame($seller->id, $payouts[0]->seller_id);
        $this->assertSame(40500, $payouts[0]->amount_cents);
        $this->assertSame('2026-08-17', $payouts[0]->period_start->format('Y-m-d'));
        $this->assertSame('2026-08-23', $payouts[0]->period_end->format('Y-m-d'));
    }

    public function test_a_payout_writes_a_matching_paid_out_entry_inside_the_period(): void
    {
        $seller = $this->seller();
        $this->deliverASale($seller, 45000, '2026-08-21 11:00:00');

        $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

        $entry = LedgerEntry::query()->where('type', LedgerEntryType::PaidOut)->sole();
        $this->assertSame(-40500, $entry->amount_cents);
        $this->assertSame($payouts[0]->id, $entry->payout_id);
        $this->assertSame('2026-08-23 23:59:59', $entry->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_running_the_same_period_twice_pays_once(): void
    {
        $this->deliverASale($this->seller(), 45000, '2026-08-21 11:00:00');
        $runWeeklyPayout = app(RunWeeklyPayout::class);
        $runWeeklyPayout($this->moment('2026-08-24 09:00:00'));

        $second = $runWeeklyPayout($this->moment('2026-08-25 09:00:00'));

        $this->assertSame([], $second);
        $this->assertSame(1, Payout::query()->count());
    }

    public function test_it_pays_each_seller_their_own_released_amount(): void
    {
        $first = $this->seller('Blue Kiln Studio');
        $second = $this->seller('Rye Press');
        $this->deliverASale($first, 45000, '2026-08-21 11:00:00');
        $this->deliverASale($second, 10000, '2026-08-21 12:00:00');

        app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

        $this->assertSame(
            [$first->id => 40500, $second->id => 9000],
            Payout::query()->orderBy('seller_id')->pluck('amount_cents', 'seller_id')->all(),
        );
    }

    public function test_money_still_held_in_escrow_is_not_paid_out(): void
    {
        $seller = $this->seller();
        app(FinalizeOrder::class)(
            $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => 45000])),
            '4242 4242 4242 4242',
            $this->moment('2026-08-20 10:00:00'),
        );

        $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

        $this->assertSame([], $payouts);
        $this->assertSame(0, Payout::query()->count());
    }

    public function test_a_delivery_after_the_period_ends_waits_for_the_next_run(): void
    {
        $this->deliverASale($this->seller(), 45000, '2026-08-24 11:00:00');

        $payouts = app(RunWeeklyPayout::class)($this->moment('2026-08-24 12:00:00'));

        $this->assertSame([], $payouts);
    }

    private function deliverASale(Seller $seller, int $priceCents, string $deliveredAt): void
    {
        $order = app(FinalizeOrder::class)(
            $this->orderFor($this->verifiedCustomer(), $this->listing($seller, ['price_cents' => $priceCents])),
            '4242 4242 4242 4242',
            $this->moment('2026-08-20 10:00:00'),
        );
        $fulfillment = app(MarkShipped::class)(
            $order->fulfillments()->sole(),
            'USPS',
            '9400111899',
            $this->moment('2026-08-20 11:00:00'),
        );
        app(ConfirmDelivered::class)($fulfillment, $this->moment($deliveredAt));
    }
}
