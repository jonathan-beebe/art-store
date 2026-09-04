<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\CustomerSegment;
use App\Domain\Seller\CustomerSortColumn;
use App\Domain\Seller\SortDirection;
use App\Domain\Seller\TableSort;
use RuntimeException;

/**
 * @param  list<SegmentLink>  $links
 */
function segmentLinkFor(array $links, CustomerSegment $segment): SegmentLink
{
    foreach ($links as $link) {
        if ($link->label === $segment->label()) {
            return $link;
        }
    }

    throw new RuntimeException("no link for {$segment->value}");
}

/**
 * @param  list<ColumnHeader>  $headers
 */
function customerHeaderFor(array $headers, CustomerSortColumn $column): ColumnHeader
{
    foreach ($headers as $header) {
        if ($header->column === $column) {
            return $header;
        }
    }

    throw new RuntimeException("no header for {$column->value}");
}

it('links one segment button per segment, marking the one in force', function (): void {
    $chrome = CustomersChrome::build([], CustomerSegment::Repeat, TableSort::of(CustomerSortColumn::Spent, SortDirection::Desc));

    expect($chrome->segments)->toHaveCount(3)
        ->and(segmentLinkFor($chrome->segments, CustomerSegment::Repeat)->active)->toBeTrue()
        ->and(segmentLinkFor($chrome->segments, CustomerSegment::All)->active)->toBeFalse()
        ->and(segmentLinkFor($chrome->segments, CustomerSegment::New)->href)->toContain('segment=new');
});

it('carries the sort through a segment link and the segment through a sort link', function (): void {
    $chrome = CustomersChrome::build(
        ['segment' => 'repeat', 'sort' => 'orders', 'dir' => 'asc'],
        CustomerSegment::Repeat,
        TableSort::of(CustomerSortColumn::Orders, SortDirection::Asc),
    );

    $newSegment = segmentLinkFor($chrome->segments, CustomerSegment::New);
    $spentHeader = customerHeaderFor($chrome->columnHeaders, CustomerSortColumn::Spent);

    expect($newSegment->href)->toContain('sort=orders')
        ->and($newSegment->href)->toContain('dir=asc')
        ->and($newSegment->href)->toContain('segment=new')
        ->and($spentHeader->href)->toContain('segment=repeat');
});

it('flips the sorted column and opens every other one descending', function (): void {
    $chrome = CustomersChrome::build([], CustomerSegment::All, TableSort::of(CustomerSortColumn::Orders, SortDirection::Desc));

    expect(customerHeaderFor($chrome->columnHeaders, CustomerSortColumn::Orders)->href)->toContain('dir=asc')
        ->and(customerHeaderFor($chrome->columnHeaders, CustomerSortColumn::Spent)->href)->toContain('dir=desc');
});

it('marks the sorted column alone with an aria-sort value', function (): void {
    $chrome = CustomersChrome::build([], CustomerSegment::All, TableSort::of(CustomerSortColumn::Name, SortDirection::Asc));

    expect(customerHeaderFor($chrome->columnHeaders, CustomerSortColumn::Name)->ariaSort)->toBe('ascending')
        ->and(customerHeaderFor($chrome->columnHeaders, CustomerSortColumn::Spent)->ariaSort)->toBe('none');
});

it('carries one header per column, in the order the table renders them', function (): void {
    $chrome = CustomersChrome::build([], CustomerSegment::All, TableSort::of(CustomerSortColumn::Spent, SortDirection::Desc));

    expect(array_map(fn (ColumnHeader $header): string => $header->column->label(), $chrome->columnHeaders))
        ->toBe(['Customer', 'Orders', 'Spent', 'Favorites', 'Last order', 'Conversations', 'Since']);
});
