<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\CarriesRefusalData;
use App\Domain\DomainRuleViolation;

/**
 * Checkout, or a stale retry at payment, turned down every line naming why.
 * `getMessage()` reads as one sentence for whoever is shown only that, and
 * `refusalData()` carries the whole list into the `order.place` or
 * `order.pay` `refused` log line's `data` (docs/spec.md §2.3) — the page
 * that sent the shopper here reads `$blocked` directly instead.
 */
final class OrderPlacementRefused extends DomainRuleViolation implements CarriesRefusalData
{
    /**
     * @param  list<BlockedLine>  $blocked
     */
    public function __construct(public readonly array $blocked)
    {
        parent::__construct(self::messageFor($blocked));
    }

    /**
     * @return array<string, mixed>
     */
    public function refusalData(): array
    {
        return [
            'blocked' => array_map(
                fn (BlockedLine $line): array => [
                    'listing_id' => $line->listingId,
                    'title' => $line->title,
                    'reason' => $line->reason->value,
                ],
                $this->blocked,
            ),
        ];
    }

    /**
     * @param  list<BlockedLine>  $blocked
     */
    private static function messageFor(array $blocked): string
    {
        $titles = array_map(fn (BlockedLine $line): string => $line->title, $blocked);

        return count($titles) === 1
            ? "“{$titles[0]}” is no longer available to buy."
            : 'Some items are no longer available to buy: '.implode(', ', $titles).'.';
    }
}
