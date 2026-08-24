<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use DateTimeImmutable;

it('is verified with a verification timestamp', function (): void {
    $purchaser = Purchaser::onAccount('cus_00000000000000000000000001', 'buyer@example.test', new DateTimeImmutable('2026-08-22 10:00:00'));

    expect($purchaser->isEmailVerified())->toBeTrue();
});

it('is unverified without a verification timestamp', function (): void {
    expect((Purchaser::onAccount('cus_00000000000000000000000001', 'buyer@example.test', null))->isEmailVerified())->toBeFalse();
});

it('buys a guest under the address they typed', function (): void {
    $purchaser = Purchaser::forCheckout('cus_00000000000000000000000001', null, null, '  Guest@Example.COM ');

    expect($purchaser->customerId)->toBe('cus_00000000000000000000000001')
        ->and($purchaser->email)->toBe('guest@example.com')
        ->and($purchaser->isEmailVerified())->toBeFalse();
});

it('buys a verified customer under the address on their account', function (): void {
    $verifiedAt = new DateTimeImmutable('2026-08-20 10:00:00');

    $purchaser = Purchaser::forCheckout('cus_00000000000000000000000001', 'ada@example.com', $verifiedAt, 'someone-else@example.com');

    expect($purchaser->email)->toBe('ada@example.com')
        ->and($purchaser->emailVerifiedAt)->toBe($verifiedAt)
        ->and($purchaser->isEmailVerified())->toBeTrue();
});
