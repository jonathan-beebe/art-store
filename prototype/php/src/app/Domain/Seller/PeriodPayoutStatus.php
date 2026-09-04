<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * How a past-periods row reads its payout column. `RunWeeklyPayout` writes
 * no row at all for a period whose balance was never payable
 * (docs/escrow.md), so a completed period with none is read as settled at
 * zero rather than as a run still owed.
 */
enum PeriodPayoutStatus
{
    case InProgress;
    case Paid;
    case Pending;
    case None;
}
