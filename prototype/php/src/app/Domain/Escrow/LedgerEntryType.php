<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

enum LedgerEntryType: string
{
    case Held = 'held';
    case Released = 'released';
    case PaidOut = 'paid_out';
}
