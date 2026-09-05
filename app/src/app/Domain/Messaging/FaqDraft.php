<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\DomainRuleViolation;

final readonly class FaqDraft
{
    public const int QUESTION_MAX_LENGTH = 500;

    public const int ANSWER_MAX_LENGTH = 2000;

    private function __construct(public string $question, public string $answer) {}

    public static function of(string $question, string $answer): self
    {
        if (mb_strlen($question) > self::QUESTION_MAX_LENGTH) {
            throw new DomainRuleViolation('A question cannot be longer than '.self::QUESTION_MAX_LENGTH.' characters.');
        }

        if (mb_strlen($answer) > self::ANSWER_MAX_LENGTH) {
            throw new DomainRuleViolation('An answer cannot be longer than '.self::ANSWER_MAX_LENGTH.' characters.');
        }

        return new self($question, $answer);
    }
}
