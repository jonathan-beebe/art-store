<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * How a past-periods row reads its payout column. `RunWeeklyPayout` writes
 * no row at all for a period whose balance was never payable
 * (docs/escrow.md); a completed period with none reads as settled at zero.
 */
enum PeriodPayoutStatus
{
    case InProgress;
    case Paid;
    case None;
}
