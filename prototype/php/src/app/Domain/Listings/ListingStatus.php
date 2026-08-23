<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use App\Domain\DomainRuleViolation;

enum ListingStatus: string
{
    case Draft = 'draft';
    case ForSale = 'for_sale';
    case Sold = 'sold';
    case Archived = 'archived';

    /**
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::ForSale, self::Archived],
            self::ForSale => [self::Sold, self::Archived],
            // A declined payment restores the stock it took, so a sold-out
            // listing goes back on the storefront.
            self::Sold => [self::ForSale],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    public function transitionTo(self $next): self
    {
        return $this->canTransitionTo($next)
            ? $next
            : throw new DomainRuleViolation("A listing cannot move from {$this->value} to {$next->value}.");
    }

    /**
     * A sold listing keeps its page so the links a buyer already followed
     * still lead somewhere; a draft or archived one was never public.
     */
    public function isOnStorefront(): bool
    {
        return $this === self::ForSale || $this === self::Sold;
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
