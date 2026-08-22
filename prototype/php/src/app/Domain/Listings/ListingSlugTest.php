<?php

namespace App\Domain\Listings;

use PHPUnit\Framework\TestCase;

final class ListingSlugTest extends TestCase
{
    public function test_it_slugs_the_title(): void
    {
        $this->assertSame('harbour-at-dusk', ListingSlug::firstFree('Harbour at Dusk', []));
    }

    public function test_it_numbers_a_slug_another_listing_already_holds(): void
    {
        $this->assertSame('harbour-at-dusk-2', ListingSlug::firstFree('Harbour at Dusk', ['harbour-at-dusk']));
    }

    public function test_it_keeps_counting_past_a_numbered_slug(): void
    {
        $taken = ['harbour-at-dusk', 'harbour-at-dusk-2', 'harbour-at-dusk-3'];

        $this->assertSame('harbour-at-dusk-4', ListingSlug::firstFree('Harbour at Dusk', $taken));
    }

    public function test_it_falls_back_to_a_word_when_the_title_slugs_to_nothing(): void
    {
        $this->assertSame('listing', ListingSlug::firstFree('—', []));
        $this->assertSame('listing', ListingSlug::base('—'));
    }

    public function test_its_base_ignores_what_is_already_taken(): void
    {
        $this->assertSame('harbour-at-dusk', ListingSlug::base('Harbour at Dusk'));
    }
}
