<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\DescriptionSectionKind;
use App\Models\DescriptionSection;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Collection;

it('builds the sections in position order with no kind chosen to add', function (): void {
    $listing = $this->listing($this->seller());
    $second = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 1]);
    $first = DescriptionSection::factory()->create(['listing_id' => $listing->id, 'position' => 0]);

    $data = DescriptionSectionIndexPageData::build($listing);

    /** @var Listing $builtListing */
    $builtListing = $data['listing'];
    /** @var Collection<int, DescriptionSection> $sections */
    $sections = $data['sections'];

    expect($builtListing->is($listing))->toBeTrue()
        ->and($sections->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($data['addKind'])->toBeNull();
});

it('resolves a query kind to add, or null for one it does not recognize', function (): void {
    $listing = $this->listing($this->seller());

    expect(DescriptionSectionIndexPageData::build($listing, 'faq')['addKind'])->toBe(DescriptionSectionKind::Faq)
        ->and(DescriptionSectionIndexPageData::build($listing, 'nonsense')['addKind'])->toBeNull();
});
