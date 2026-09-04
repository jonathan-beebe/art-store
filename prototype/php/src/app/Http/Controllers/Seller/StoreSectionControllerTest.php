<?php

declare(strict_types=1);

use App\Actions\Store\StartStore;
use App\Domain\Store\StoreSectionKind;
use App\Models\Seller;
use App\Models\StoreImage;
use App\Models\StoreProfile;
use App\Models\StoreSection;

/**
 * A signed-in seller and the store their first visit would mint.
 *
 * @return array{Seller, StoreProfile}
 */
$storekeeper = function (): array {
    $seller = Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']);

    return [$seller, app(StartStore::class)($seller)];
};

it('adds a section of the kind the seller picked', function (string $kind) use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();

    $this->actingAs($seller, 'seller')
        ->post('/seller/store/sections', ['kind' => $kind])
        ->assertRedirect(route('seller.store.show'));

    expect($profile->sections()->sole()->kind->value)->toBe($kind);
})->with(['story', 'gallery']);

it('refuses a kind the page has no renderer for', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();

    $this->actingAs($seller, 'seller')
        ->post('/seller/store/sections', ['kind' => 'podcast'])
        ->assertSessionHasErrors('kind');

    expect($profile->sections()->count())->toBe(0);
});

it('saves the words in a story', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id]);

    $this->actingAs($seller, 'seller')
        ->put("/seller/store/sections/{$section->id}", [
            'heading' => 'How the Burrow makes things',
            'body' => 'Everything here is made in the kitchen.',
        ])
        ->assertRedirect(route('seller.store.show'));

    $saved = $section->fresh();
    expect($saved?->heading)->toBe('How the Burrow makes things')
        ->and($saved?->body)->toBe('Everything here is made in the kitchen.');
});

it('places the store\'s pictures in a gallery', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    $section = StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id]);
    $first = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $second = StoreImage::factory()->create(['store_profile_id' => $profile->id]);

    $this->actingAs($seller, 'seller')
        ->put("/seller/store/sections/{$section->id}", ['images' => [$second->id, $first->id]])
        ->assertRedirect(route('seller.store.show'));

    expect($section->sectionImages()->pluck('store_image_id')->all())->toBe([$second->id, $first->id]);
});

it('takes a section off the page', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id]);

    $this->actingAs($seller, 'seller')
        ->delete("/seller/store/sections/{$section->id}")
        ->assertRedirect(route('seller.store.show'));

    expect(StoreSection::find($section->id))->toBeNull();
});

it('answers not found for another seller\'s section', function (string $method, string $suffix) use ($storekeeper): void {
    [$seller] = $storekeeper();
    $section = StoreSection::factory()->create(['store_profile_id' => StoreProfile::factory()->create()->id]);

    $this->actingAs($seller, 'seller')
        ->call($method, "/seller/store/sections/{$section->id}{$suffix}")
        ->assertNotFound();
})->with([
    'saving it' => ['PUT', ''],
    'removing it' => ['DELETE', ''],
    'moving it' => ['POST', '/reorder'],
]);

it('shows the page a seller built', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    StoreSection::factory()->create([
        'store_profile_id' => $profile->id,
        'heading' => 'How the Burrow makes things',
        'kind' => StoreSectionKind::Story,
    ]);

    $this->actingAs($seller, 'seller')
        ->get('/seller/store')
        ->assertOk()
        ->assertSee('How the Burrow makes things');
});
