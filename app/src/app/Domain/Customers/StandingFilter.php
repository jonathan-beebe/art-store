<?php

declare(strict_types=1);

namespace App\Domain\Customers;

/**
 * How the admin customers list is narrowed. `All` is what an empty filter
 * means, so the console's "All customers" option and a bare `?standing=`
 * reach the same page.
 */
enum StandingFilter: string
{
    case All = 'all';
    case Verified = 'verified';
    case Anonymous = 'anonymous';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All customers',
            self::Verified => 'Verified',
            self::Anonymous => 'Anonymous',
            self::Blocked => 'Blocked',
        };
    }
}
