<?php

declare(strict_types=1);

namespace App\Domain\Shop;

use DomainException;

/**
 * What a storefront visitor asked to see: free text over the catalogue and a
 * medium to narrow it to.
 */
final readonly class ListingSearch
{
    private function __construct(public ?string $term, public ?string $medium) {}

    public static function fromInput(?string $term, ?string $medium): self
    {
        return new self(self::filled($term), self::filled($medium));
    }

    public function hasTerm(): bool
    {
        return $this->term !== null;
    }

    public function hasMedium(): bool
    {
        return $this->medium !== null;
    }

    public function likePattern(): string
    {
        if ($this->term === null) {
            throw new DomainException('A search without a term has no pattern.');
        }

        // SQLite LIKE has no escape character unless the query names one, so a
        // wildcard a visitor typed is dropped rather than escaped.
        $literal = preg_replace('/\s+/', ' ', str_replace(['%', '_'], ' ', $this->term));

        return '%'.trim((string) $literal).'%';
    }

    private static function filled(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
