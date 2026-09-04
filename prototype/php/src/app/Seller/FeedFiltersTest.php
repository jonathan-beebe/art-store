<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ActivityKind;
use App\Models\Customer;

it('offers All and one button per kind, All in force with no kind chosen', function (): void {
    $customer = Customer::factory()->create();

    $links = FeedFilters::for('seller.customers.show', ['customer' => $customer->id], null);

    expect(array_map(fn (NavLink $link): string => $link->label, $links))
        ->toBe(['All', 'Browsing', 'Order', 'Shipping', 'Messages'])
        ->and($links[0]->active)->toBeTrue()
        ->and($links[0]->href)->not->toContain('kind=');
});

it('marks the chosen kind and names it on every other button\'s link', function (): void {
    $customer = Customer::factory()->create();

    $links = FeedFilters::for('seller.customers.show', ['customer' => $customer->id], ActivityKind::Shipping);

    $shipping = array_values(array_filter($links, fn (NavLink $link): bool => $link->label === 'Shipping'));
    $messages = array_values(array_filter($links, fn (NavLink $link): bool => $link->label === 'Messages'));

    expect($links[0]->active)->toBeFalse()
        ->and($shipping[0]->active)->toBeTrue()
        ->and($shipping[0]->href)->toContain('kind=shipping')
        ->and($messages[0]->active)->toBeFalse()
        ->and($messages[0]->href)->toContain('kind=messages');
});
