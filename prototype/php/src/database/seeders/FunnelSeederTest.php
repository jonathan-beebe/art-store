<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Funnel;

it('seeds the storefront funnel with the default step list', function (): void {
    $this->seed(FunnelSeeder::class);

    $funnel = Funnel::query()->where('slug', 'storefront')->sole();

    expect($funnel->name)->toBe('Storefront')
        ->and($funnel->steps)->toBe(['listing.view', 'listing.cart_add', 'checkout.open', 'order.place', 'order.pay'])
        ->and($funnel->position)->toBe(1);
});

it('changes nothing on a second run', function (): void {
    $this->seed(FunnelSeeder::class);
    $this->seed(FunnelSeeder::class);

    expect(Funnel::query()->where('slug', 'storefront')->count())->toBe(1);
});
