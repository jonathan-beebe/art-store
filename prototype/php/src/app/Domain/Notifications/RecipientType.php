<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

enum RecipientType: string
{
    case Seller = 'seller';
    case Customer = 'customer';
}
