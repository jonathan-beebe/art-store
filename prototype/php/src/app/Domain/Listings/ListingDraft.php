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
        public ?string $dimensions,
        public Money $price,
        // Null: made to order — no fixed count, reached only through the
        // "Made to order" checkbox on create or Basics.
        public ?int $quantity,
        public ?string $categoryId = null,
    ) {}

    /**
     * Three of the five fields are strings that transpose without a word of
     * complaint, so the one way in takes them by name. `categoryId` defaults
     * to null — an uncategorized listing is as valid as it was before the
     * taxonomy existed.
     */
    public static function of(
        string $title,
        ?string $description,
        ?string $dimensions,
        Money $price,
        ?int $quantity,
        ?string $categoryId = null,
    ): self {
        return new self($title, $description, $dimensions, $price, $quantity, $categoryId);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'dimensions' => $this->dimensions,
            'price_cents' => $this->price->cents,
            'quantity' => $this->quantity,
            'category_id' => $this->categoryId,
        ];
    }
}
