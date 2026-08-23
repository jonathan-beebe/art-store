<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use DateTimeImmutable;

it('is verified with a verification timestamp', function (): void {
    $purchaser = Purchaser::onAccount(7, 'buyer@example.test', new DateTimeImmutable('2026-08-22 10:00:00'));

    expect($purchaser->isEmailVerified())->toBeTrue();
});

it('is unverified without a verification timestamp', function (): void {
    expect((Purchaser::onAccount(7, 'buyer@example.test', null))->isEmailVerified())->toBeFalse();
});

it('buys a guest under the address they typed', function (): void {
    $purchaser = Purchaser::forCheckout(7, null, null, '  Guest@Example.COM ');

    expect($purchaser->customerId)->toBe(7)
        ->and($purchaser->email)->toBe('guest@example.com')
        ->and($purchaser->isEmailVerified())->toBeFalse();
});

it('buys a verified customer under the address on their account', function (): void {
    $verifiedAt = new DateTimeImmutable('2026-08-20 10:00:00');

    $purchaser = Purchaser::forCheckout(7, 'ada@example.com', $verifiedAt, 'someone-else@example.com');

    expect($purchaser->email)->toBe('ada@example.com')
        ->and($purchaser->emailVerifiedAt)->toBe($verifiedAt)
        ->and($purchaser->isEmailVerified())->toBeTrue();
});
