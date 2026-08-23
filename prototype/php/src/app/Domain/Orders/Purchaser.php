<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use DateTimeImmutable;

final readonly class Purchaser
{
    public function __construct(
        public int $customerId,
        public ?string $email,
        public ?DateTimeImmutable $emailVerifiedAt,
    ) {}

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
}
