<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * A deterministic hash of one cart line's configuration — the chosen
 * variant, the unit it claims (if the variant is serialized), and its
 * modifier answers — so two lines with different configurations of the same
 * listing can share `cart_items.listing_id` without colliding, once FEAT-027
 * widens the unique index to `(cart_id, listing_id, fingerprint)`. A listing
 * with no axes has no variant to choose, so it always resolves the same
 * fingerprint and that widened index still behaves exactly like today's
 * `(cart_id, listing_id)` for it — the legacy path this ticket must not
 * disturb.
 */
final readonly class CartLineFingerprint
{
    private function __construct(public string $value) {}

    /**
     * @param  array<string, string>  $answers  modifier id => answer, sorted for determinism
     */
    public static function of(?string $variantId, ?string $unitId, array $answers): self
    {
        ksort($answers);

        return new self(hash('sha256', json_encode(
            ['variant' => $variantId, 'unit' => $unitId, 'answers' => $answers],
            JSON_THROW_ON_ERROR,
        )));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
