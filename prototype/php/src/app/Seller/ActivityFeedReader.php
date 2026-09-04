<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ActivityFeed;

/**
 * Every source, merged. The sources are asked in the order their rows tie
 * in — browsing, order, shipping, messages — so a page reading the same
 * scope twice reads the same feed.
 *
 * A filter narrows what the pure feed hands back, never what the sources
 * return, so a page can never disagree with itself about what happened.
 */
final readonly class ActivityFeedReader
{
    public function __construct(
        private AnalyticsSource $browsing,
        private OrderSource $orders,
        private FulfillmentSource $shipping,
        private MessagingSource $messages,
    ) {}

    public function read(FeedScope $scope): ActivityFeed
    {
        return ActivityFeed::merge(
            $this->browsing->events($scope),
            $this->orders->events($scope),
            $this->shipping->events($scope),
            $this->messages->events($scope),
        );
    }
}
