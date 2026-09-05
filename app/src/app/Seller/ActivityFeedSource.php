<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\FeedEvent;

/**
 * One place a feed's rows come from. A source answers for its own scope and
 * knows nothing of the others; the merge and the filter are pure.
 */
interface ActivityFeedSource
{
    /**
     * @return list<FeedEvent>
     */
    public function events(FeedScope $scope): array;
}
