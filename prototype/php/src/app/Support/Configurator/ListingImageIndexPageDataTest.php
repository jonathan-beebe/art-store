<?php

declare(strict_types=1);

namespace App\Support\Configurator;

use App\Models\ListingImage;
use Illuminate\Database\Eloquent\Collection;

it('lists the listings images cover-first', function (): void {
    $listing = $this->listing($this->seller());
    $second = $this->listingImage($listing, ['position' => 1]);
    $first = $this->listingImage($listing, ['position' => 0]);

    $data = ListingImageIndexPageData::build($listing);

    /** @var Collection<int, ListingImage> $images */
    $images = $data['images'];

    expect($images->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($data['maxImages'])->toBe(ListingImage::MAX_PER_LISTING);
});
