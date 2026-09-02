<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Analytics\AnalyticsEventName;

final readonly class ListingEventCount
{
    private function __construct(public AnalyticsEventName $name, public int $count) {}

    public static function of(AnalyticsEventName $name, int $count): self
    {
        return new self($name, $count);
    }

    public function label(): string
    {
        return $this->name->label();
    }
}
