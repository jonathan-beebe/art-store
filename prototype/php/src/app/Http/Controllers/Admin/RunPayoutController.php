<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Domain\Money\Money;
use App\Domain\Reports\PayoutSummary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunPayoutRequest;
use App\Models\Payout;
use Illuminate\Http\RedirectResponse;

final class RunPayoutController extends Controller
{
    public function __invoke(RunPayoutRequest $request, RunWeeklyPayout $runWeeklyPayout): RedirectResponse
    {
        $payouts = $runWeeklyPayout($request->asOf($this->now()));

        $summary = PayoutSummary::of(array_map(fn (Payout $payout): Money => $payout->amount(), $payouts));

        return redirect()
            ->route('admin.payouts.index')
            ->with('status', "Weekly payout run: {$summary->count} payout(s) totalling {$summary->total}.");
    }
}
