<?php

declare(strict_types=1);

namespace App\Domain\Orders;

final readonly class ShippingAddress
{
    public function __construct(
        public string $name,
        public string $line1,
        public ?string $line2,
        public string $city,
        public string $region,
        public string $postalCode,
        public string $country,
    ) {}
}
