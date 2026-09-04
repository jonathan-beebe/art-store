<?php

declare(strict_types=1);

use App\Models\Seller;
use App\Policies\SellerPolicy;

it('lets a seller view their own page', function (): void {
    $seller = Seller::factory()->create();

    expect((new SellerPolicy)->view($seller, $seller)->allowed())->toBeTrue();
});

it('answers not found for another seller', function (): void {
    $response = (new SellerPolicy)->view(Seller::factory()->create(), Seller::factory()->create());

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});
