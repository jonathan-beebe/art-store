<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\Auth\EmailAddress;
use DateTimeImmutable;

final readonly class Purchaser
{
    public function __construct(
        public int $customerId,
        public ?string $email,
        public ?DateTimeImmutable $emailVerifiedAt,
    ) {}

    /**
     * A verified customer buys under the address on their account, so a
     * submitted field cannot move an order onto someone else's identity.
     */
    public static function forCheckout(
        int $customerId,
        ?string $accountEmail,
        ?DateTimeImmutable $emailVerifiedAt,
        string $submittedEmail,
    ): self {
        return $emailVerifiedAt === null
            ? new self($customerId, EmailAddress::normalize($submittedEmail), null)
            : new self($customerId, $accountEmail, $emailVerifiedAt);
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
}
