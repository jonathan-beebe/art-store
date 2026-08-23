<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum ActorType: string
{
    case Seller = 'seller';
    case Customer = 'customer';

    private const SELLER_PATH_PREFIX = '/seller';

    /**
     * The seller portal owns every path under /seller, and a customer's
     * session holds no seller guard, so a customer sent there would land on a
     * sign-in wall.
     */
    public function allowsPath(string $path): bool
    {
        $sellerPath = $path === self::SELLER_PATH_PREFIX
            || str_starts_with($path, self::SELLER_PATH_PREFIX.'/');

        return match ($this) {
            self::Seller => true,
            self::Customer => ! $sellerPath,
        };
    }

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
