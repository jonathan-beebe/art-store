<?php

declare(strict_types=1);

namespace App\Domain\Payments;

final readonly class CardDecision
{
    private function __construct(
        public bool $isApproved,
        public string $lastFour,
        public ?DeclineReason $declineReason,
    ) {}

    public static function approved(string $lastFour): self
    {
        return new self(true, $lastFour, null);
    }

    public static function declined(string $lastFour, DeclineReason $reason): self
    {
        return new self(false, $lastFour, $reason);
    }
}
