<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Orders\FulfillmentStatus;

final readonly class FulfillmentStatusCount
{
    private function __construct(public FulfillmentStatus $status, public int $count) {}

    public static function of(FulfillmentStatus $status, int $count): self
    {
        return new self($status, $count);
    }

    public function label(): string
    {
        return $this->status->label();
    }
}
