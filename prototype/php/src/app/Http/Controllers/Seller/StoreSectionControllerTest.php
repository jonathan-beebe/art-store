<?php

declare(strict_types=1);

use App\Actions\Store\StartStore;
use App\Domain\Store\StoreSectionKind;
use App\Http\Requests\Seller\StoreSectionRequest;
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
        ->assertSessionHasErrors('kind', errorBag: StoreSectionRequest::errorBagFor(null));

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

it('places a gallery in the order the seller numbered', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    $section = StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id]);
    $first = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $second = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $third = StoreImage::factory()->create(['store_profile_id' => $profile->id]);

    $this->actingAs($seller, 'seller')
        ->put("/seller/store/sections/{$section->id}", [
            'images' => [$first->id, $second->id, $third->id],
            'order' => [$third->id => 0, $first->id => 1, $second->id => 2],
        ])
        ->assertRedirect(route('seller.store.show'));

    expect($section->sectionImages()->pluck('store_image_id')->all())
        ->toBe([$third->id, $first->id, $second->id]);
});

it('sorts a picture the form gave no number last', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    $section = StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id]);
    $numbered = StoreImage::factory()->create(['store_profile_id' => $profile->id]);
    $unnumbered = StoreImage::factory()->create(['store_profile_id' => $profile->id]);

    $this->actingAs($seller, 'seller')->put("/seller/store/sections/{$section->id}", [
        'images' => [$unnumbered->id, $numbered->id],
        'order' => [$numbered->id => 0],
    ]);

    expect($section->sectionImages()->pluck('store_image_id')->all())
        ->toBe([$numbered->id, $unnumbered->id]);
});

it('offers a place for every picture on the form', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id]);
    $image = StoreImage::factory()->create(['store_profile_id' => $profile->id]);

    $this->actingAs($seller, 'seller')
        ->get('/seller/store')
        ->assertOk()
        ->assertSee("order[{$image->id}]", false);
});

it('keeps a body too long for the column in the form and says so', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id, 'body' => 'The stored story.']);
    $tooLong = str_repeat('a', StoreSection::MAX_BODY_LENGTH + 1);

    $this->actingAs($seller, 'seller')
        ->from('/seller/store')
        ->put("/seller/store/sections/{$section->id}", ['body' => $tooLong])
        ->assertRedirect('/seller/store')
        ->assertSessionHasErrors('body', errorBag: "section-{$section->id}");

    expect($section->fresh()?->body)->toBe('The stored story.');
});

it('shows the words a seller typed back on the section that refused them', function () use ($storekeeper): void {
    [$seller, $profile] = $storekeeper();
    $section = StoreSection::factory()->create(['store_profile_id' => $profile->id]);
    StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id, 'position' => 1, 'heading' => 'The studio']);

    $this->actingAs($seller, 'seller')
        ->from('/seller/store')
        ->put("/seller/store/sections/{$section->id}", [
            'heading' => 'A heading worth keeping',
            'body' => str_repeat('a', StoreSection::MAX_BODY_LENGTH + 1),
        ]);

    $this->actingAs($seller, 'seller')
        ->get('/seller/store')
        ->assertOk()
        ->assertSee('A heading worth keeping', false)
        ->assertSee('The studio', false);
});
