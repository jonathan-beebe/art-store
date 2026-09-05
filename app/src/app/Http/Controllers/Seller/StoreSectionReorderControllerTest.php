<?php

declare(strict_types=1);

use App\Actions\Store\StartStore;
use App\Models\Seller;
use App\Models\StoreProfile;
use App\Models\StoreSection;

/**
 * A signed-in seller whose store page holds a story then a gallery.
 *
 * @return array{Seller, StoreProfile, StoreSection, StoreSection}
 */
$page = function (): array {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);
    $profile = app(StartStore::class)($seller);

    return [
        $seller,
        $profile,
        StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => 0]),
        StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id, 'position' => 1]),
    ];
};

it('moves a section up through the order of the page', function () use ($page): void {
    [$seller, $profile, $first, $second] = $page();

    $this->actingAs($seller, 'seller')
        ->post("/seller/store/sections/{$second->id}/reorder", ['direction' => 'up'])
        ->assertRedirect(route('seller.store.show'));

    expect($profile->sections()->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('moves a section down through the order of the page', function () use ($page): void {
    [$seller, $profile, $first, $second] = $page();

    $this->actingAs($seller, 'seller')
        ->post("/seller/store/sections/{$first->id}/reorder", ['direction' => 'down'])
        ->assertRedirect(route('seller.store.show'));

    expect($profile->sections()->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('refuses a direction that is neither up nor down', function () use ($page): void {
    [$seller, $profile, $first, $second] = $page();

    $this->actingAs($seller, 'seller')
        ->post("/seller/store/sections/{$first->id}/reorder", ['direction' => 'sideways'])
        ->assertSessionHasErrors('direction');

    expect($profile->sections()->pluck('id')->all())->toBe([$first->id, $second->id]);
});
