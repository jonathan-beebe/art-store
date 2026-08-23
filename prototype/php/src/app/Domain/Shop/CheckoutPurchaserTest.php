<?php

declare(strict_types=1);

namespace App\Domain\Shop;

use DateTimeImmutable;

it('buys a guest under the address they typed', function (): void {
    $purchaser = CheckoutPurchaser::forCustomer(7, null, null, '  Guest@Example.COM ');

    expect($purchaser->customerId)->toBe(7)
        ->and($purchaser->email)->toBe('guest@example.com')
        ->and($purchaser->isEmailVerified())->toBeFalse();
});

it('buys a verified customer under the address on their account', function (): void {
    $verifiedAt = new DateTimeImmutable('2026-08-20 10:00:00');

    $purchaser = CheckoutPurchaser::forCustomer(7, 'ada@example.com', $verifiedAt, 'someone-else@example.com');

    expect($purchaser->email)->toBe('ada@example.com')
        ->and($purchaser->emailVerifiedAt)->toBe($verifiedAt)
        ->and($purchaser->isEmailVerified())->toBeTrue();
});
