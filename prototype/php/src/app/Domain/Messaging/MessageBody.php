<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\DomainRuleViolation;

final readonly class MessageBody
{
    public const int MAX_LENGTH = 2000;

    private function __construct(public string $value) {}

    public static function of(string $value): self
    {
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new DomainRuleViolation('A message cannot be longer than '.self::MAX_LENGTH.' characters.');
        }

        return new self($value);
    }
}
