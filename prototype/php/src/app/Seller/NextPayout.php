<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Seller\PayoutEstimate;
use App\Models\Fulfillment;
use App\Models\Payout;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The next weekly payout the earnings page leads with: the seller's ledger
 * balance available to pay out, folded into a {@see PayoutEstimate} against
 * the payout period in progress.
 */
final readonly class NextPayout
{
    private function __construct(public PayoutEstimate $estimate) {}

    public static function for(Seller $seller, DateTimeImmutable $now): self
    {
        $currentPeriod = PayoutPeriod::containing($now);
        $lastPayout = $seller->payouts()->latest('period_end')->first();

        $releasedCount = $seller->fulfillments()
            ->where('status', FulfillmentStatus::Delivered)
            ->when(
                $lastPayout,
                /** @param Builder<Fulfillment> $query */
                fn (Builder $query, Payout $payout): Builder => $query->where('delivered_at', '>', $payout->period_end->endOfDay()),
            )
            ->count();

        return new self(PayoutEstimate::from($seller->escrowBalance(), $currentPeriod, $releasedCount));
    }
}
