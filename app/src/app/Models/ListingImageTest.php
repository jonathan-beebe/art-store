<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\QueryException;

it('serves its file from the public disk as a relative path', function (): void {
    $image = ListingImage::factory()->create(['path' => 'listings/heron.png']);

    expect($image->url())->toBe('/storage/listings/heron.png');
});

it('rejects a second image at the same position on the same listing', function (): void {
    $listing = $this->listing($this->seller());
    $this->listingImage($listing, ['position' => 0]);

    expect(fn () => $this->listingImage($listing, ['position' => 0]))
        ->toThrow(QueryException::class);
});
