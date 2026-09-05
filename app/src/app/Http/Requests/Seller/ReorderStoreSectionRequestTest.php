<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\StoreProfile;
use App\Models\StoreSection;

it('requires a direction', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->storeFor($seller);
    $profile = $seller->storeProfile()->sole();
    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/store/sections/{$section->id}/reorder", []);

    $response->assertSessionHasErrors('direction');
});

it('refuses a direction other than up or down', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->storeFor($seller);
    $profile = $seller->storeProfile()->sole();
    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/store/sections/{$section->id}/reorder", [
        'direction' => 'sideways',
    ]);

    $response->assertSessionHasErrors('direction');
});

it('accepts up and down as legal directions', function (string $direction): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->storeFor($seller);
    $profile = $seller->storeProfile()->sole();
    $first = StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => 0]);
    $second = StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => 1]);
    $target = $direction === 'up' ? $second : $first;

    $response = $this->actingAs($seller, 'seller')->post("/seller/store/sections/{$target->id}/reorder", [
        'direction' => $direction,
    ]);

    $response->assertSessionHasNoErrors();
})->with(['up', 'down']);

it('answers another sellers section as not found', function (): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->storeFor($seller);
    $otherProfile = StoreProfile::factory()->create();
    $otherSection = StoreSection::factory()->create(['store_profile_id' => $otherProfile->id]);

    $response = $this->actingAs($seller, 'seller')->post("/seller/store/sections/{$otherSection->id}/reorder", [
        'direction' => 'up',
    ]);

    $response->assertNotFound();
});
