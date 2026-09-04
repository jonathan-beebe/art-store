<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ActivityFeed;

/**
 * Every source, merged. The sources are asked in the order their rows tie
 * in — browsing, order, shipping, messages, the order
 * `App\Providers\ActivityFeedServiceProvider` binds them in — so a page
 * reading the same scope twice reads the same feed.
 *
 * A filter narrows what the pure feed hands back, never what the sources
 * return, so a page can never disagree with itself about what happened.
 */
final readonly class ActivityFeedReader
{
    /**
     * @var list<ActivityFeedSource>
     */
    private array $sources;

    public function __construct(ActivityFeedSource ...$sources)
    {
        $this->sources = array_values($sources);
    }

    public function read(FeedScope $scope): ActivityFeed
    {
        return ActivityFeed::merge(...array_map(
            fn (ActivityFeedSource $source): array => $source->events($scope),
            $this->sources,
        ));
    }
}
