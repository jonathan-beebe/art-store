<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

enum RecipientType: string
{
    case Seller = 'seller';
    case Customer = 'customer';

    /**
     * The `notifications` column that names this recipient; exactly one of the
     * two is set per row.
     */
    public function column(): string
    {
        return match ($this) {
            self::Seller => 'seller_id',
            self::Customer => 'customer_id',
        };
    }
}
