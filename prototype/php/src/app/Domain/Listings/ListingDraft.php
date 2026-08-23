<?php

declare(strict_types=1);

namespace App\Domain\Listings;

use App\Domain\Money\Money;

/**
 * The listing fields a seller types into the create or edit form, validated and
 * converted. Status, slug, and image are the portal's to decide, not the form's.
 */
final readonly class ListingDraft
{
    private function __construct(
        public string $title,
        public ?string $description,
        public ?string $medium,
        public ?string $dimensions,
        public Money $price,
        public int $quantity,
    ) {}

    /**
     * Four of the six fields are strings that transpose without a word of
     * complaint, so the one way in takes them by name.
     */
    public static function of(
        string $title,
        ?string $description,
        ?string $medium,
        ?string $dimensions,
        Money $price,
        int $quantity,
    ): self {
        return new self($title, $description, $medium, $dimensions, $price, $quantity);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'medium' => $this->medium,
            'dimensions' => $this->dimensions,
            'price_cents' => $this->price->cents,
            'quantity' => $this->quantity,
        ];
    }
}
