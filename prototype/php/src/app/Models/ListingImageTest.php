<?php

declare(strict_types=1);

namespace App\Models;

it('belongs to the listing it was uploaded for', function (): void {
    $listing = $this->listing($this->seller());
    $image = $this->listingImage($listing);

    expect($image->listing()->first()?->id)->toBe($listing->id);
});

it('serves its file from the public disk', function (): void {
    $image = ListingImage::factory()->create(['path' => 'listings/heron.png']);

    expect($image->url())->toEndWith('/storage/listings/heron.png');
});
