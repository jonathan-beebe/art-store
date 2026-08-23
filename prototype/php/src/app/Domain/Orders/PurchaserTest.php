<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use DateTimeImmutable;

it('is verified with a verification timestamp', function (): void {
    $purchaser = new Purchaser(7, 'buyer@example.test', new DateTimeImmutable('2026-08-22 10:00:00'));

    expect($purchaser->isEmailVerified())->toBeTrue();
});

it('is unverified without a verification timestamp', function (): void {
    expect((new Purchaser(7, 'buyer@example.test', null))->isEmailVerified())->toBeFalse();
});
