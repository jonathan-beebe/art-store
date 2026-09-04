<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreVisibility;
use App\Models\StoreProfile;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
$form = function (array $overrides = []): array {
    return $overrides + [
        'name' => 'The Burrow Craftworks',
        'slug' => 'the-burrow-craftworks',
        'tagline' => 'Knitted, thrown, and carved at the Burrow',
        'location' => 'Ottery St Catchpole, Devon',
        'visibility' => StoreVisibility::Hidden->value,
        'links' => [],
    ];
};

it('refuses an address outside the regex or past the length ceiling', function (string $slug) use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->put('/seller/store', $form(['slug' => $slug]));

    $response->assertSessionHasErrors('slug');
})->with([
    'uppercase' => 'The-Burrow',
    'a space' => 'the burrow',
    'below the floor' => 'tb',
    'one past the ceiling' => str_repeat('a', 61),
]);

it('requires a name', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->put('/seller/store', $form(['name' => '']));

    $response->assertSessionHasErrors('name');
});

it('refuses a tagline past the ceiling', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->put('/seller/store', $form([
        'tagline' => str_repeat('a', StoreProfile::MAX_TAGLINE_LENGTH + 1),
    ]));

    $response->assertSessionHasErrors('tagline');
});

it('refuses a visibility value outside published or hidden', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->put('/seller/store', $form(['visibility' => 'archived']));

    $response->assertSessionHasErrors('visibility');
});

it('requires the website link to be a url', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->put('/seller/store', $form([
        'links' => [StoreLinkKind::Website->value => 'not a url'],
    ]));

    $response->assertSessionHasErrors('links.website');
});

it('refuses a url in the instagram field', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $response = $this->actingAs($seller, 'seller')->put('/seller/store', $form([
        'links' => [StoreLinkKind::Instagram->value => 'https://instagram.com/theburrow'],
    ]));

    $response->assertSessionHasErrors('links.instagram');
});

it('trims a blank tagline and location down to null', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->actingAs($seller, 'seller')->get('/seller/store');

    $this->actingAs($seller, 'seller')->put('/seller/store', $form([
        'tagline' => '   ',
        'location' => '   ',
    ]));

    $profile = $seller->storeProfile()->sole();
    expect($profile->tagline)->toBeNull()
        ->and($profile->location)->toBeNull();
});
