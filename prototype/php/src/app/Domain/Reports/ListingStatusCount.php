<?php

namespace App\Domain\Reports;

use App\Domain\Listings\ListingStatus;

final readonly class ListingStatusCount
{
    public function __construct(public ListingStatus $status, public int $count) {}

    public function label(): string
    {
        return StatusLabel::of($this->status);
    }
}
