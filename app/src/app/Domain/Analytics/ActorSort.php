<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * The all-actors page's sort control: `Active` orders by total events in
 * the range, `Recent` orders by last seen —
 * {@see \App\Analytics\Admin\ActorList} breaks a tie in either order by
 * actor id, so paging never reshuffles two actors that read as equal.
 */
enum ActorSort: string
{
    case Active = 'active';
    case Recent = 'recent';
}
