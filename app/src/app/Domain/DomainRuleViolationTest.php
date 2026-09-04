<?php

declare(strict_types=1);

namespace App\Domain;

use DomainException;

it('is a domain exception', function (): void {
    expect(new DomainRuleViolation('That listing is no longer for sale.'))
        ->toBeInstanceOf(DomainException::class);
});

it('carries the message the person who tripped the rule reads', function (): void {
    expect((new DomainRuleViolation('That listing is no longer for sale.'))->getMessage())
        ->toBe('That listing is no longer for sale.');
});
