<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Listings\ListingStatus;
use App\Domain\Orders\FulfillmentStatus;
use App\Domain\Orders\OrderStatus;
use PHPUnit\Framework\TestCase;

final class StatusLabelTest extends TestCase
{
    public function test_it_reads_a_single_word_status_as_a_sentence(): void
    {
        $this->assertSame('Draft', StatusLabel::of(ListingStatus::Draft));
    }

    public function test_it_replaces_the_underscores_of_a_multi_word_status(): void
    {
        $this->assertSame('For sale', StatusLabel::of(ListingStatus::ForSale));
        $this->assertSame('Awaiting shipment', StatusLabel::of(FulfillmentStatus::AwaitingShipment));
        $this->assertSame('Pending verification', StatusLabel::of(OrderStatus::PendingVerification));
    }
}
