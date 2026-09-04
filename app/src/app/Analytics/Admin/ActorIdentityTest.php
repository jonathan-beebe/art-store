<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Models\Customer;

it('reads a verified customer by its verified email', function (): void {
    $customer = Customer::factory()->create(['email' => 'hermione@example.com']);

    $identity = ActorIdentity::of($customer);

    expect($identity->kind)->toBe('verified')
        ->and($identity->who)->toBe('hermione@example.com');
});

it('reads an anonymous customer as never having signed in', function (): void {
    $customer = Customer::factory()->anonymous()->create();

    $identity = ActorIdentity::of($customer);

    expect($identity->kind)->toBe('anonymous')
        ->and($identity->who)->toBe('never signed in');
});
