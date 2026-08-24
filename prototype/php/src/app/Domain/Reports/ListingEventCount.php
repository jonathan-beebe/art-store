<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingEventType;

final readonly class ListingEventCount
{
    private function __construct(public ListingEventType $type, public int $count) {}

    public static function of(ListingEventType $type, int $count): self
    {
        return new self($type, $count);
    }

    public function label(): string
    {
        return $this->type->label();
    }
}
