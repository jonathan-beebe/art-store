<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\Auth\EmailNormalizer;
use DateTimeImmutable;

final readonly class Purchaser
{
    private function __construct(
        public string $customerId,
        public ?string $email,
        public ?DateTimeImmutable $emailVerifiedAt,
    ) {}

    /**
     * The purchaser their account already describes, with no form field in
     * play — a re-paid order, a seeded history, a test fixture.
     */
    public static function onAccount(string $customerId, ?string $email, ?DateTimeImmutable $emailVerifiedAt): self
    {
        return new self($customerId, $email, $emailVerifiedAt);
    }

    /**
     * A verified customer buys under the address on their account, so a
     * submitted field cannot move an order onto someone else's identity.
     */
    public static function forCheckout(
        string $customerId,
        ?string $accountEmail,
        ?DateTimeImmutable $emailVerifiedAt,
        string $submittedEmail,
    ): self {
        return $emailVerifiedAt === null
            ? new self($customerId, EmailNormalizer::normalize($submittedEmail), null)
            : new self($customerId, $accountEmail, $emailVerifiedAt);
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
}
