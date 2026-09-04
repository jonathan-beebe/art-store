<?php

declare(strict_types=1);

namespace App\Domain\Customers;

/**
 * What a customer merge writes to the cart and favorites, worked out before
 * anything is touched. Pure: it judges the lines and ids it is given,
 * reading nothing itself — `MergeAnonymousCustomer` reads both customers'
 * cart items, favorites, and the stock behind them, folds what it read into
 * this shape, and applies the result with updates and deletes.
 *
 * Pinned by its tests: a shopper merged from any anonymous cart keeps the
 * same cart and the same favorites regardless of which side held them.
 */
final readonly class CustomerMergePlan
{
    /**
     * @param  list<CustomerCartLine>  $cartLines  the verified customer's cart after the fold, one line per (listing, fingerprint)
     * @param  list<string>  $favoritesToMove  anonymous favorites the verified customer does not already have — repoint these rows
     * @param  list<string>  $favoritesToDrop  anonymous favorites that duplicate one the verified customer already has — delete these rows
     */
    private function __construct(
        public array $cartLines,
        public array $favoritesToMove,
        public array $favoritesToDrop,
    ) {}

    /**
     * @param  list<CustomerCartLine>  $verifiedCartLines
     * @param  list<CustomerCartLine>  $anonymousCartLines
     * @param  list<string>  $verifiedFavoriteListingIds
     * @param  list<string>  $anonymousFavoriteListingIds
     * @param  array<string, int|null>  $stockByListing  units in stock, by listing id; a listing absent from this map, or mapped to null (made to order), contributes no cap
     */
    public static function for(
        array $verifiedCartLines,
        array $anonymousCartLines,
        array $verifiedFavoriteListingIds,
        array $anonymousFavoriteListingIds,
        array $stockByListing,
    ): self {
        $favorites = self::partitionFavorites($verifiedFavoriteListingIds, $anonymousFavoriteListingIds);

        return new self(
            self::foldCartLines($verifiedCartLines, $anonymousCartLines, $stockByListing),
            $favorites['favoritesToMove'],
            $favorites['favoritesToDrop'],
        );
    }

    /**
     * Sums quantity per (listing, fingerprint) across both carts in the
     * order each was first seen — verified lines before anonymous ones —
     * clamps the sum to stock, and drops anything that lands at zero. A
     * listing already removed from the storefront is not special-cased: its
     * row still carries the stock it held before removal, so the line
     * survives the fold at that quantity the same way it would survive
     * sitting untouched in a single cart across a removal —
     * `OrderPlacementPlan` is what marks it blocked when checkout is
     * attempted, not the fold. The stock clamp reads the listing's own
     * quantity for every line of that listing, configured or not — the same
     * approximation `AddToCart` accepts at add time; a variant's own tighter
     * bound is enforced there, not folded in here.
     *
     * @param  list<CustomerCartLine>  $verifiedLines
     * @param  list<CustomerCartLine>  $anonymousLines
     * @param  array<string, int|null>  $stockByListing
     * @return list<CustomerCartLine>
     */
    private static function foldCartLines(array $verifiedLines, array $anonymousLines, array $stockByListing): array
    {
        $order = [];
        $quantityByKey = [];
        $lineByKey = [];

        foreach ([...$verifiedLines, ...$anonymousLines] as $line) {
            $key = $line->listingId.'|'.$line->fingerprint;

            if (! array_key_exists($key, $quantityByKey)) {
                $order[] = $key;
                $quantityByKey[$key] = 0;
                $lineByKey[$key] = $line;
            }

            $quantityByKey[$key] += $line->quantity;
        }

        $lines = [];
        foreach ($order as $key) {
            $summed = $quantityByKey[$key];
            $original = $lineByKey[$key];
            $stock = $stockByListing[$original->listingId] ?? null;
            $quantity = $stock === null ? $summed : min($summed, max($stock, 0));

            if ($quantity > 0) {
                $lines[] = new CustomerCartLine(
                    $original->listingId,
                    $quantity,
                    $original->fingerprint,
                    $original->variantId,
                    $original->unitId,
                    $original->configurationJson,
                    $original->answersJson,
                );
            }
        }

        return $lines;
    }

    /**
     * Splits the anonymous customer's favorites into the ones that can move
     * (nothing named that listing yet) and the ones that must be dropped
     * instead (the verified customer already favorited it, so moving the row
     * would duplicate it), de-duplicating the anonymous side first so the
     * same listing favorited twice moves once.
     *
     * @param  list<string>  $verifiedIds
     * @param  list<string>  $anonymousIds
     * @return array{favoritesToMove: list<string>, favoritesToDrop: list<string>}
     */
    private static function partitionFavorites(array $verifiedIds, array $anonymousIds): array
    {
        $alreadyFavorited = array_flip($verifiedIds);
        $seen = [];
        $favoritesToMove = [];
        $favoritesToDrop = [];

        foreach ($anonymousIds as $listingId) {
            if (isset($seen[$listingId])) {
                continue;
            }

            $seen[$listingId] = true;

            if (isset($alreadyFavorited[$listingId])) {
                $favoritesToDrop[] = $listingId;
            } else {
                $favoritesToMove[] = $listingId;
            }
        }

        return ['favoritesToMove' => $favoritesToMove, 'favoritesToDrop' => $favoritesToDrop];
    }
}
