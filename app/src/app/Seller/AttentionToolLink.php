<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Fulfillment\LaneFilter;
use App\Domain\Seller\AttentionTool;

/**
 * Where one focus group's header opens: the seller-portal route for the
 * tool that clears it. {@see AttentionTool} names the four tools; the
 * routes they resolve to are a design fact, held here.
 */
final class AttentionToolLink
{
    private function __construct() {} // @codeCoverageIgnore

    public static function hrefOf(AttentionTool $tool): string
    {
        return match ($tool) {
            AttentionTool::Orders => route('seller.orders.index', ['lane' => LaneFilter::ToShip->value]),
            AttentionTool::Messages => route('seller.messages.index'),
            AttentionTool::Earnings => route('seller.earnings'),
            AttentionTool::Listings => route('seller.listings.index'),
        };
    }
}
