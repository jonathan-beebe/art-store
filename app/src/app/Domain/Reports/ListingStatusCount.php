<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingStatus;

final readonly class ListingStatusCount
{
    private function __construct(public ListingStatus $status, public int $count) {}

    public static function of(ListingStatus $status, int $count): self
    {
        return new self($status, $count);
    }

    public function label(): string
    {
        return $this->status->label();
    }
}
