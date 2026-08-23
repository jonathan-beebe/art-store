<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Domain\Reports\PayoutSummary;
use App\Http\Controllers\Controller;
use App\Models\Payout;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;

final class PayoutController extends Controller
{
    public function __invoke(RunWeeklyPayout $runWeeklyPayout): RedirectResponse
    {
        $payouts = $runWeeklyPayout(new DateTimeImmutable(now()->toDateTimeString()));

        $summary = PayoutSummary::of(array_map(fn (Payout $payout): int => $payout->amount_cents, $payouts));

        return redirect()
            ->route('seller.earnings')
            ->with('status', "Weekly payout run: {$summary->count} payout(s) totalling {$summary->total->format()}.");
    }
}
