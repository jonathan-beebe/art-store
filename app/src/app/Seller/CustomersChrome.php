<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\CustomerRow;
use App\Domain\Seller\CustomerSegment;
use App\Domain\Seller\CustomerSortColumn;
use App\Domain\Seller\TableSort;

/**
 * The customers table's header: the segment control and the sortable
 * column headers, built once per request from the round-tripped filters
 * and the segment and sort in force. The controller and the view read one
 * object, and the enums stay in one place.
 */
final readonly class CustomersChrome
{
    /**
     * @param  list<NavLink>  $segments
     * @param  TableSort<CustomerRow>  $sort
     * @param  list<ColumnHeader>  $columnHeaders
     */
    private function __construct(
        public CustomerSegment $segment,
        public array $segments,
        public TableSort $sort,
        public array $columnHeaders,
    ) {}

    /**
     * @param  array<string, string>  $roundTripped  the query the index route was reached with
     * @param  TableSort<CustomerRow>  $sort
     */
    public static function build(array $roundTripped, CustomerSegment $segment, TableSort $sort): self
    {
        return new self(
            segment: $segment,
            segments: NavLinks::for(
                routeName: 'seller.customers.index',
                without: collect($roundTripped)->except('segment')->all(),
                param: 'segment',
                cases: CustomerSegment::cases(),
                label: fn (CustomerSegment $case): string => $case->label(),
                value: fn (CustomerSegment $case): string => $case->value,
                active: fn (CustomerSegment $case): bool => $case === $segment,
            ),
            sort: $sort,
            columnHeaders: ColumnHeaders::for('seller.customers.index', $roundTripped, $sort, CustomerSortColumn::cases()),
        );
    }
}
