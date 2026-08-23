<?php

declare(strict_types=1);

namespace App\Models;

it('is anonymous when it has no email', function (): void {
    expect((new Customer)->isAnonymous())->toBeTrue();
});

it('is not anonymous once it has an email', function (): void {
    $customer = new Customer(['email' => 'shopper@example.com']);

    expect($customer->isAnonymous())->toBeFalse();
});
