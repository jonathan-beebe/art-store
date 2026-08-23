<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Domain\Reports\PayoutSummary;
use App\Models\Payout;
use Illuminate\Http\RedirectResponse;

final class PayoutController extends SellerController
{
    public function __invoke(RunWeeklyPayout $runWeeklyPayout): RedirectResponse
    {
        $payouts = $runWeeklyPayout($this->now());

        $summary = PayoutSummary::of(array_map(fn (Payout $payout): int => $payout->amount_cents, $payouts));

        return redirect()
            ->route('seller.earnings')
            ->with('status', "Weekly payout run: {$summary->count} payout(s) totalling {$summary->total->format()}.");
    }
}
