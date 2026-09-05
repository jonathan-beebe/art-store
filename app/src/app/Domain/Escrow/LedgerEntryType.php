<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

enum LedgerEntryType: string
{
    case Held = 'held';
    case Released = 'released';
    case PaidOut = 'paid_out';
    case Refunded = 'refunded';

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    /**
     * Where a seller's money for one parcel stands, as the order page's
     * payment card says it.
     */
    public function escrowState(): string
    {
        return match ($this) {
            self::Held => 'Held until delivery',
            self::Released => 'Released to your balance',
            self::PaidOut => 'Paid out',
            self::Refunded => 'Returned to the buyer',
        };
    }
}
