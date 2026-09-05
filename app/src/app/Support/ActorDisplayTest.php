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

it('initials a two-word name from its first letters', function (): void {
    $seller = $this->seller('Blue Kiln Studio');

    expect(ActorDisplay::initialsOf($seller))->toBe('BK');
});

it('initials a one-word name alone', function (): void {
    $admin = Admin::factory()->create(['name' => 'Cher']);

    expect(ActorDisplay::initialsOf($admin))->toBe('C');
});

it('stops at two initials for a name with more words', function (): void {
    $admin = Admin::factory()->create(['name' => 'Anna Maria Schmunk']);

    expect(ActorDisplay::initialsOf($admin))->toBe('AM');
});

it('initials no actor from the deleted-account name it falls back to', function (): void {
    expect(ActorDisplay::initialsOf(null))->toBe('DA');
});

it('reduces a name a page already holds to two initials', function (string $name, string $expected): void {
    expect(ActorDisplay::initialsFor($name))->toBe($expected);
})->with([
    'the desk' => [ActorDisplay::SUPPORT_DESK, 'AS'],
    'two words' => ['Luna Lovegood', 'LL'],
    'one word' => ['Hagrid', 'H'],
    'padded' => ['  Ginny   Weasley  ', 'GW'],
]);
