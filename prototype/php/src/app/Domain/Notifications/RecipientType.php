<?php

namespace App\Domain\Notifications;

enum RecipientType: string
{
    case Seller = 'seller';
    case Customer = 'customer';
}
