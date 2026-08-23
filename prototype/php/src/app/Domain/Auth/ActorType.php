<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum ActorType: string
{
    case Seller = 'seller';
    case Customer = 'customer';

    public function guard(): string
    {
        return $this->value;
    }

    public function homeRouteName(): string
    {
        return match ($this) {
            self::Seller => 'seller.dashboard',
            self::Customer => 'shop.account',
        };
    }

    public function loginRouteName(): string
    {
        return match ($this) {
            self::Seller => 'auth.seller.login',
            self::Customer => 'auth.customer.login',
        };
    }
}
