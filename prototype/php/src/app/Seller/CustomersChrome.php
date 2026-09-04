<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\CustomerSegment;
use App\Domain\Seller\CustomerSort;
use App\Domain\Seller\CustomerSortColumn;

/**
 * The customers table's header: the segment control and the sortable
 * column headers, built once per request from the round-tripped filters
 * and the segment and sort in force. The controller and the view read one
 * object, and the enums stay in one place.
 */
final readonly class CustomersChrome
{
    /**
     * @param  list<SegmentLink>  $segments
     * @param  list<ColumnHeader>  $columnHeaders
     */
    private function __construct(
        public CustomerSegment $segment,
        public array $segments,
        public CustomerSort $sort,
        public array $columnHeaders,
    ) {}

    /**
     * @param  array<string, string>  $roundTripped  the query the index route was reached with
     */
    public static function build(array $roundTripped, CustomerSegment $segment, CustomerSort $sort): self
    {
        return new self(
            segment: $segment,
            segments: self::segmentLinks($roundTripped, $segment),
            sort: $sort,
            columnHeaders: self::columnHeaders($roundTripped, $sort),
        );
    }

    /**
     * @param  array<string, string>  $roundTripped
     * @return list<SegmentLink>
     */
    private static function segmentLinks(array $roundTripped, CustomerSegment $current): array
    {
        $without = collect($roundTripped)->except('segment')->all();

        return array_map(fn (CustomerSegment $segment): SegmentLink => new SegmentLink(
            label: $segment->label(),
            href: route('seller.customers.index', [...$without, 'segment' => $segment->value]),
            active: $segment === $current,
        ), CustomerSegment::cases());
    }

    /**
     * Every column links to itself sorted: the sorted one flips direction,
     * every other one opens descending.
     *
     * @param  array<string, string>  $roundTripped
     * @return list<ColumnHeader>
     */
    private static function columnHeaders(array $roundTripped, CustomerSort $sort): array
    {
        $without = collect($roundTripped)->except(['sort', 'dir'])->all();

        return array_map(fn (CustomerSortColumn $column): ColumnHeader => new ColumnHeader(
            column: $column,
            href: route('seller.customers.index', [...$without, 'sort' => $column->value, 'dir' => $sort->nextDirectionFor($column)->value]),
            ariaSort: $sort->ariaSort($column) ?? 'none',
        ), CustomerSortColumn::cases());
    }
}
