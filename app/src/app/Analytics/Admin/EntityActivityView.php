<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\BarStripBar;

/**
 * A listing's or an actor's whole drill-in page —
 * {@see EntityActivity::forListing()}/{@see EntityActivity::forActor()}'s
 * result: the identity card's facts, the flagged-actor banner, the five
 * range tiles, the strip, the event feed, and the visits panel. One shape
 * for both entity kinds — `$kind` is `listing` for a listing page,
 * `anonymous` or `verified` for an actor page, the same vocabulary
 * {@see ActorIdentity::of()} and the admin chrome's badge tints use
 * elsewhere. `$visits` is empty for a listing; a visit belongs to a
 * session.
 */
final readonly class EntityActivityView
{
    /**
     * @param  list<EntityFact>  $facts
     * @param  list<EventTile>  $tiles  exactly five, in the page's own order
     * @param  list<BarStripBar>  $strip
     * @param  list<EntityFeedRow>  $feed  newest first, capped at {@see EntityActivity::FEED_LIMIT}
     * @param  list<\App\Analytics\ActorVisitRow>  $visits  newest first, capped at {@see EntityActivity::VISITS_LIMIT}
     */
    public function __construct(
        public string $kind,
        public string $id,
        public string $title,
        public array $facts,
        public bool $flagged,
        public string $flagText,
        public array $tiles,
        public string $stripTitle,
        public array $strip,
        public string $stripFirst,
        public string $stripLast,
        public array $feed,
        public string $feedCaption,
        public array $visits,
    ) {}
}
