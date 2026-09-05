<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * The seller tool that clears one focus group of the dashboard's "Needs
 * your attention" row.
 */
enum AttentionTool
{
    case Orders;
    case Messages;
    case Earnings;
    case Listings;
}
