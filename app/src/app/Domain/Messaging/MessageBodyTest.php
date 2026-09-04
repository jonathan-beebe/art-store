<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\DomainRuleViolation;

it('holds a message within the limit', function (): void {
    expect(MessageBody::of('Is this still available?')->value)->toBe('Is this still available?');
});

it('accepts a message at exactly the limit', function (): void {
    $value = str_repeat('a', MessageBody::MAX_LENGTH);

    expect(MessageBody::of($value)->value)->toBe($value);
});

it('rejects a message past the limit', function (): void {
    $value = str_repeat('a', MessageBody::MAX_LENGTH + 1);

    expect(fn () => MessageBody::of($value))->toThrow(DomainRuleViolation::class);
});
