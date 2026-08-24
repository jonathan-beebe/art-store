<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Orders\OrderStatus;

final readonly class OrderStatusCount
{
    private function __construct(public OrderStatus $status, public int $count) {}

    public static function of(OrderStatus $status, int $count): self
    {
        return new self($status, $count);
    }

    public function label(): string
    {
        return $this->status->label();
    }
}
