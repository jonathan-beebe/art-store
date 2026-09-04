<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreSlug;
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

it('refuses a value outside its rule', function (array $overrides, string $errorField) use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->storeFor($seller);

    $response = $this->actingAs($seller, 'seller')->put('/seller/store', $form($overrides));

    $response->assertSessionHasErrors($errorField);
})->with([
    'slug uppercase' => [['slug' => 'The-Burrow'], 'slug'],
    'slug with a space' => [['slug' => 'the burrow'], 'slug'],
    'slug below the floor' => [['slug' => 'tb'], 'slug'],
    'slug one past the ceiling' => [['slug' => str_repeat('a', StoreSlug::MAX_LENGTH + 1)], 'slug'],
    'no name' => [['name' => ''], 'name'],
    'tagline one past the ceiling' => [['tagline' => str_repeat('a', StoreProfile::MAX_TAGLINE_LENGTH + 1)], 'tagline'],
    'visibility outside published or hidden' => [['visibility' => 'archived'], 'visibility'],
    'website not a url' => [['links' => [StoreLinkKind::Website->value => 'not a url']], 'links.website'],
    'instagram a url' => [['links' => [StoreLinkKind::Instagram->value => 'https://instagram.com/theburrow']], 'links.instagram'],
]);

it('accepts a value exactly at the ceiling', function (array $overrides) use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->storeFor($seller);

    $response = $this->actingAs($seller, 'seller')->put('/seller/store', $form($overrides));

    $response->assertSessionHasNoErrors();
})->with([
    'slug at the floor' => [['slug' => str_repeat('a', StoreSlug::MIN_LENGTH)]],
    'slug at the ceiling' => [['slug' => str_repeat('a', StoreSlug::MAX_LENGTH)]],
    'tagline at the ceiling' => [['tagline' => str_repeat('a', StoreProfile::MAX_TAGLINE_LENGTH)]],
]);

it('trims a blank tagline and location down to null', function () use ($form): void {
    $seller = $this->seller('The Burrow Craftworks');
    $this->storeFor($seller);

    $this->actingAs($seller, 'seller')->put('/seller/store', $form([
        'tagline' => '   ',
        'location' => '   ',
    ]));

    $profile = $seller->storeProfile()->sole();
    expect($profile->tagline)->toBeNull()
        ->and($profile->location)->toBeNull();
});
