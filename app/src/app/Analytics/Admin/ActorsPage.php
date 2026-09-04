<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Support\Page;

/**
 * One page of the all-actors table — {@see ActorList::forRange()}'s
 * result: the slice of {@see ActorSummary} rows a viewer sees, and the
 * {@see Page} `x-admin.pager` renders alongside it.
 */
final readonly class ActorsPage
{
    /**
     * @param  list<ActorSummary>  $rows
     */
    public function __construct(
        public Page $page,
        public array $rows,
    ) {}
}
