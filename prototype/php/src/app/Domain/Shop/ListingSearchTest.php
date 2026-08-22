<?php

namespace App\Domain\Shop;

use DomainException;
use PHPUnit\Framework\TestCase;

final class ListingSearchTest extends TestCase
{
    public function test_it_reads_a_term_and_a_medium(): void
    {
        $search = ListingSearch::fromInput('  harbour  ', ' oil ');

        $this->assertTrue($search->hasTerm());
        $this->assertTrue($search->hasMedium());
        $this->assertSame('harbour', $search->term);
        $this->assertSame('oil', $search->medium);
    }

    public function test_it_treats_blank_input_as_no_filter(): void
    {
        $search = ListingSearch::fromInput('   ', null);

        $this->assertFalse($search->hasTerm());
        $this->assertFalse($search->hasMedium());
    }

    public function test_it_wraps_the_term_in_wildcards(): void
    {
        $this->assertSame('%harbour%', ListingSearch::fromInput('harbour', null)->likePattern());
    }

    public function test_it_drops_wildcards_the_visitor_typed(): void
    {
        $this->assertSame('%50 off%', ListingSearch::fromInput('50% _off', null)->likePattern());
    }

    public function test_it_refuses_a_pattern_without_a_term(): void
    {
        $this->expectException(DomainException::class);

        ListingSearch::fromInput(null, 'oil')->likePattern();
    }
}
