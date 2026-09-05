<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * What a listing-question thread offers to carry onto a published FAQ entry:
 * the opening message read as the question, the seller's latest reply read
 * as the answer, and which message the answer was lifted from.
 */
final readonly class FaqPrefill
{
    private function __construct(
        public string $question,
        public string $answer,
        public string $sourceMessageId,
    ) {}

    public static function of(string $question, string $answer, string $sourceMessageId): self
    {
        return new self($question, $answer, $sourceMessageId);
    }
}
