<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * A variant's combination, named by the option values it holds rather than by
 * a compound label string — the primitive that replaces "Gold - Inside" and
 * "3 US - 4mm". Sorted so the same combination always names itself the same
 * way regardless of the order its axes were chosen in, which is what lets
 * `variants.combo_key` carry a UNIQUE index. An axis-free listing's one
 * variant (the legacy path) holds the empty key.
 */
final readonly class ComboKey
{
    private function __construct(public string $value) {}

    /**
     * @param  list<string>  $optionValueIds
     */
    public static function of(array $optionValueIds): self
    {
        $sorted = $optionValueIds;
        sort($sorted, SORT_STRING);

        return new self(implode('/', $sorted));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
