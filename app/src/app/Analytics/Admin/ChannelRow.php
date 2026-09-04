<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

/**
 * One row of the channel report {@see ChannelTable::forRange()} builds:
 * one origin — a campaign, a search engine, a social network, a referral,
 * or direct — and what it produced this range against what it produced
 * the range before.
 */
final readonly class ChannelRow
{
    public function __construct(
        public string $channelKey,
        public string $label,
        public ChannelMetric $visitors,
        public ChannelMetric $views,
        public ChannelMetric $cartAdds,
        public ChannelMetric $ordersPlaced,
        public ChannelMetric $ordersPaid,
    ) {}
}
