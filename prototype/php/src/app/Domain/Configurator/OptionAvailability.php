<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * Whether one option value is choosable given every other axis's current
 * selection: greyed out for "not offered" (no variant covers that
 * combination, or the seller disabled the one that does) or "out of stock"
 * (the variant covers it but has nothing left), or selectable outright.
 */
final readonly class OptionAvailability
{
    private function __construct(public bool $selectable, public ?string $reason) {}

    public static function selectable(): self
    {
        return new self(true, null);
    }

    public static function notOffered(): self
    {
        return new self(false, 'not offered');
    }

    public static function outOfStock(): self
    {
        return new self(false, 'out of stock');
    }

    /**
     * @param  array<string, bool>  $enabledByComboKey  every combo key with a variant row, mapped to that variant's `enabled`
     * @param  array<string, bool>  $availableByComboKey  the same combo keys, mapped to {@see VariantAvailability}
     */
    public static function resolve(string $comboKey, array $enabledByComboKey, array $availableByComboKey): self
    {
        if (! array_key_exists($comboKey, $enabledByComboKey) || ! $enabledByComboKey[$comboKey]) {
            return self::notOffered();
        }

        return $availableByComboKey[$comboKey] ?? false ? self::selectable() : self::outOfStock();
    }
}
