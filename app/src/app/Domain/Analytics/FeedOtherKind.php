<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * The other party in one entity page's event feed row
 * ({@see \App\Analytics\Admin\EntityFeedRow::$otherKind}). On a listing's
 * or a store's page, it is the actor that caused the event. On an actor's
 * page, it is whatever the event's own subject names — a listing, an
 * order, a cart, a store, or a help article. A listing or a store links
 * only while it still exists; a cart or a help article carries no page of
 * its own to link to; an actor's or an order's row is never missing since
 * neither kind of row is ever deleted.
 */
enum FeedOtherKind: string
{
    case Actor = 'actor';
    case Listing = 'listing';
    case Order = 'order';
    case Cart = 'cart';
    case Store = 'store';
    case HelpArticle = 'help_article';

    /**
     * `EntityActivity::feed()` reads this to choose which of its two
     * row-building strategies applies.
     */
    public function isListing(): bool
    {
        return $this === self::Listing;
    }

    /**
     * A help article carries no admin page of its own to link to.
     */
    public function isHelpArticle(): bool
    {
        return $this === self::HelpArticle;
    }
}
