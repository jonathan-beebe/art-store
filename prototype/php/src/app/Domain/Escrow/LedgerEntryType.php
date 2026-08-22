<?php

namespace App\Domain\Escrow;

enum LedgerEntryType: string
{
    case Held = 'held';
    case Released = 'released';
    case PaidOut = 'paid_out';
}
