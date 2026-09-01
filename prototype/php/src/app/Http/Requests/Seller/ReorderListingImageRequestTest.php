<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

it('refuses a direction other than up or down', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $image = $this->listingImage($listing);

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images/{$image->id}/reorder", [
        'direction' => 'sideways',
    ]);

    $response->assertSessionHasErrors('direction');
});

it('validates up and down as legal directions', function (string $direction): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $first = $this->listingImage($listing, ['position' => 0]);
    $second = $this->listingImage($listing, ['position' => 1]);
    $target = $direction === 'up' ? $second : $first;

    $response = $this->actingAs($seller, 'seller')->post("/seller/listings/{$listing->id}/images/{$target->id}/reorder", [
        'direction' => $direction,
    ]);

    $response->assertSessionHasNoErrors();
})->with(['up', 'down']);
