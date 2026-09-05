<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Fulfillment\LaneFilter;
use App\Domain\Seller\AttentionTool;

it('opens each tool at the route that clears its queue', function (): void {
    expect(AttentionToolLink::hrefOf(AttentionTool::Orders))
        ->toBe(route('seller.orders.index', ['lane' => LaneFilter::ToShip->value]))
        ->and(AttentionToolLink::hrefOf(AttentionTool::Messages))->toBe(route('seller.messages.index'))
        ->and(AttentionToolLink::hrefOf(AttentionTool::Earnings))->toBe(route('seller.earnings'))
        ->and(AttentionToolLink::hrefOf(AttentionTool::Listings))->toBe(route('seller.listings.index'));
});
