<?php

declare(strict_types=1);

use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreVisibility;
use App\Models\StoreProfile;

/**
 * The Store form as the screen posts it, with the case's own overrides on
 * top.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
$form = fn (array $overrides = []): array => $overrides + [
    'name' => 'The Burrow Craftworks',
    'slug' => 'the-burrow-craftworks',
    'tagline' => 'Knitted, thrown, and carved at the Burrow',
    'location' => 'Ottery St Catchpole, Devon',
    'visibility' => StoreVisibility::Hidden->value,
    'links' => [],
];

it('sends a signed-out visitor to the sign-in page', function (): void {
    $this->get('/seller/store')->assertRedirect(route('auth.seller.login'));
});

it('mints the store on the first visit and shows the form', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    $this->actingAs($seller, 'seller')->get('/seller/store')
        ->assertOk()
        ->assertSee('Your store')
        ->assertSee('the-burrow-craftworks');

    expect(StoreProfile::query()->where('seller_id', $seller->id)->count())->toBe(1);
});

it('mints the store once however often the screen is opened', function (): void {
    $seller = $this->seller('The Burrow Craftworks');

    $this->actingAs($seller, 'seller')->get('/seller/store')->assertOk();
    $this->actingAs($seller, 'seller')->get('/seller/store')->assertOk();

    expect(StoreProfile::count())->toBe(1);
});

it('saves the identity a seller typed', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $this->actingAs($seller, 'seller')
        ->put('/seller/store', $form(['name' => 'Burrow Works', 'tagline' => 'Made by the fire', 'location' => 'Devon']))
        ->assertRedirect(route('seller.store.show'));

    $profile = $seller->storeProfile()->sole();
    expect($profile->name)->toBe('Burrow Works')
        ->and($profile->tagline)->toBe('Made by the fire')
        ->and($profile->location)->toBe('Devon');
});

it('publishes and hides the store', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $this->actingAs($seller, 'seller')->put('/seller/store', $form(['visibility' => 'published']));
    expect($seller->storeProfile()->sole()->isPublished())->toBeTrue();

    $this->actingAs($seller, 'seller')->put('/seller/store', $form(['visibility' => 'hidden']));
    expect($seller->storeProfile()->sole()->isPublished())->toBeFalse();
});

it('keeps the day a store first opened when it is hidden and published again', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $this->actingAs($seller, 'seller')->put('/seller/store', $form(['visibility' => 'published']));
    $first = $seller->storeProfile()->sole()->published_at;

    $this->actingAs($seller, 'seller')->put('/seller/store', $form(['visibility' => 'hidden']));
    $this->actingAs($seller, 'seller')->put('/seller/store', $form(['visibility' => 'published']));

    expect($seller->storeProfile()->sole()->published_at?->toDateTimeString())->toBe($first?->toDateTimeString());
});

it('keeps the old address when the seller moves the store', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $this->actingAs($seller, 'seller')->put('/seller/store', $form(['slug' => 'burrow-works']));

    $profile = $seller->storeProfile()->sole();
    expect($profile->slug)->toBe('burrow-works')
        ->and($profile->slugs()->retired()->pluck('slug')->all())->toBe(['the-burrow-craftworks']);
});

it('refuses a save with no name', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $this->actingAs($seller, 'seller')
        ->put('/seller/store', $form(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('refuses an address another store answers to', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $this->actingAs($this->seller('Nine Owls'), 'seller')->get('/seller/store');

    $this->actingAs($seller, 'seller')
        ->put('/seller/store', $form(['slug' => 'nine-owls']))
        ->assertSessionHasErrors('slug');
});

it('refuses an address another store retired', function () use ($form): void {
    $other = $this->seller('Nine Owls');
    $this->actingAs($other, 'seller')->get('/seller/store');
    $this->actingAs($other, 'seller')->put('/seller/store', $form(['name' => 'Nine Owls', 'slug' => 'nine-owls-nest']));

    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');
    $this->actingAs($seller, 'seller')
        ->put('/seller/store', $form(['slug' => 'nine-owls']))
        ->assertSessionHasErrors('slug');
});

it('takes the address the store already holds without complaint', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $this->actingAs($seller, 'seller')
        ->put('/seller/store', $form())
        ->assertSessionHasNoErrors();
});

it('keeps the links a seller filled in and drops the ones they cleared', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $this->actingAs($seller, 'seller')->put('/seller/store', $form(['links' => [
        StoreLinkKind::Website->value => 'https://theburrow.example',
        StoreLinkKind::Instagram->value => '@theburrowcraftworks',
    ]]));
    expect($seller->storeProfile()->sole()->links()->count())->toBe(2);

    $this->actingAs($seller, 'seller')->put('/seller/store', $form(['links' => [
        StoreLinkKind::Website->value => 'https://theburrow.example',
    ]]));

    $links = $seller->storeProfile()->sole()->links()->get();
    expect($links)->toHaveCount(1)
        ->and($links->first()?->kind)->toBe(StoreLinkKind::Website);
});
