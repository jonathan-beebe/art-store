<?php

namespace App\Actions\Escrow;

use App\Domain\Escrow\LedgerBalance;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Escrow\PayoutPeriod;
use App\Domain\Money\Money;
use App\Models\LedgerEntry;
use App\Models\Payout;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RunWeeklyPayout
{
    /**
     * @return list<Payout>
     */
    public function __invoke(DateTimeImmutable $asOf): array
    {
        $period = PayoutPeriod::endingBefore($asOf);

        return DB::transaction(fn (): array => $this->balancesBySeller($period)
            ->filter(fn (LedgerBalance $balance): bool => $balance->isPayable())
            ->map(fn (LedgerBalance $balance, int $sellerId): Payout => $this->payOut($sellerId, $balance->available, $period, $asOf))
            ->values()
            ->all());
    }

    /**
     * @return Collection<int, LedgerBalance>
     */
    private function balancesBySeller(PayoutPeriod $period): Collection
    {
        return LedgerEntry::query()
            ->occurredBy($period->end)
            ->get()
            ->groupBy('seller_id')
            ->map(fn (Collection $entries): LedgerBalance => LedgerBalance::from(
                $entries->map(fn (LedgerEntry $entry): LedgerMovement => $entry->toMovement())->all(),
            ));
    }

    private function payOut(int $sellerId, Money $available, PayoutPeriod $period, DateTimeImmutable $asOf): Payout
    {
        $payout = Payout::create([
            'seller_id' => $sellerId,
            'period_start' => $period->start,
            'period_end' => $period->end,
            'amount_cents' => $available->cents,
            'paid_at' => $asOf,
        ]);

        $movement = LedgerMovement::payout($available);

        LedgerEntry::create([
            'seller_id' => $sellerId,
            'payout_id' => $payout->id,
            'type' => $movement->type,
            'amount_cents' => $movement->amount->cents,
            // Dated inside the period it settles so a re-run of the same period
            // sees the money as already paid.
            'occurred_at' => $period->end,
        ]);

        return $payout;
    }
}
