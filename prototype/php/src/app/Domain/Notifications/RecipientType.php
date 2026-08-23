<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * The two sides of the marketplace a notification can be addressed to. Each
 * value is the morph alias stored in `notifications.notifiable_type`, so a
 * value here is a persisted word: changing one orphans rows.
 */
enum RecipientType: string
{
    case Seller = 'seller';
    case Customer = 'customer';
}
