<?php

declare(strict_types=1);

namespace App\Support\Shop;

use App\Domain\Messaging\ConversationKind;

/**
 * The short pill word an inbox row and a thread header wear for a
 * conversation's kind — a storefront-only reading of `ConversationKind`,
 * since the seller and admin sites label the same four kinds their own way.
 */
final class ConversationKindLabel
{
    private function __construct() {} // @codeCoverageIgnore

    public static function of(ConversationKind $kind): string
    {
        return match ($kind) {
            ConversationKind::ListingQuestion => 'Question',
            ConversationKind::Fulfillment => 'Order',
            ConversationKind::AdminSeller, ConversationKind::AdminCustomer => 'Support',
        };
    }
}
