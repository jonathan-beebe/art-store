<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum ActorType: string
{
    case Seller = 'seller';
    case Customer = 'customer';
    case Admin = 'admin';

    private const SELLER_PATH_PREFIX = '/seller';

    private const ADMIN_PATH_PREFIX = '/admin';

    /**
     * Each actor keeps to their own site: a session that only the other
     * guard holds would wall them at the door, so a magic link never sends
     * them there. A customer holds neither the seller nor the admin guard;
     * a seller and an admin each hold the one guard the other lacks.
     */
    public function allowsPath(string $path): bool
    {
        $sellerPath = self::hasPrefix($path, self::SELLER_PATH_PREFIX);
        $adminPath = self::hasPrefix($path, self::ADMIN_PATH_PREFIX);

        return match ($this) {
            self::Seller => ! $adminPath,
            self::Customer => ! $sellerPath && ! $adminPath,
            self::Admin => ! $sellerPath,
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
            self::Admin => 'admin.dashboard',
        };
    }

    public function loginRouteName(): string
    {
        return match ($this) {
            self::Seller => 'auth.seller.login',
            self::Customer => 'auth.customer.login',
            self::Admin => 'auth.admin.login',
        };
    }

    private static function hasPrefix(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }
}
