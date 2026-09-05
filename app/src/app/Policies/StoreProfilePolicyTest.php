<?php

declare(strict_types=1);

use App\Models\Seller;
use App\Models\StoreProfile;
use App\Policies\StoreProfilePolicy;

it('lets a seller view their own store', function (): void {
    $seller = Seller::factory()->create();
    $profile = StoreProfile::factory()->create(['seller_id' => $seller->id]);

    expect((new StoreProfilePolicy)->view($seller, $profile)->allowed())->toBeTrue();
});

it('lets a seller change their own store', function (): void {
    $seller = Seller::factory()->create();
    $profile = StoreProfile::factory()->create(['seller_id' => $seller->id]);

    expect((new StoreProfilePolicy)->update($seller, $profile)->allowed())->toBeTrue();
});

it('answers not found when another seller views a store', function (): void {
    $response = (new StoreProfilePolicy)->view(Seller::factory()->create(), StoreProfile::factory()->create());

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});

it('answers not found when another seller changes a store', function (): void {
    $response = (new StoreProfilePolicy)->update(Seller::factory()->create(), StoreProfile::factory()->create());

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->toBe(404);
});
