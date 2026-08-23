<?php

declare(strict_types=1);

namespace App\Domain\Orders;

final readonly class ShippingAddress
{
    private function __construct(
        public string $name,
        public string $line1,
        public ?string $line2,
        public string $city,
        public string $region,
        public string $postalCode,
        public string $country,
    ) {}

    /**
     * Seven strings in a row transpose without a word of complaint, so the one
     * way in takes them by name.
     */
    public static function to(
        string $name,
        string $line1,
        ?string $line2,
        string $city,
        string $region,
        string $postalCode,
        string $country,
    ): self {
        return new self($name, $line1, $line2, $city, $region, $postalCode, $country);
    }

    /**
     * @return array<string, string|null>
     */
    public function attributes(): array
    {
        return [
            'shipping_name' => $this->name,
            'shipping_line1' => $this->line1,
            'shipping_line2' => $this->line2,
            'shipping_city' => $this->city,
            'shipping_region' => $this->region,
            'shipping_postal_code' => $this->postalCode,
            'shipping_country' => $this->country,
        ];
    }
}
