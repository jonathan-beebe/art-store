<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use App\Models\Customer;

it('reads a customer by name, falling back to their id', function (): void {
    $named = Customer::factory()->create(['name' => 'Ada Lovelace']);
    $unnamed = Customer::factory()->create(['name' => null]);

    expect(ActorDisplay::nameOf($named))->toBe('Ada Lovelace')
        ->and(ActorDisplay::nameOf($unnamed))->toBe('Customer '.$unnamed->id);
});

it('reads a seller or an admin by their display name', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $admin = Admin::factory()->create(['name' => 'Nia Ops']);

    expect(ActorDisplay::nameOf($seller))->toBe('Blue Kiln Studio')
        ->and(ActorDisplay::nameOf($admin))->toBe('Nia Ops');
});

it('names no actor as a deleted account', function (): void {
    expect(ActorDisplay::nameOf(null))->toBe('Deleted account');
});
