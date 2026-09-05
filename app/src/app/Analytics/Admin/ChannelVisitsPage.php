<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Paging\Page;

/**
 * One channel's visits for a range, paged — {@see ChannelVisits::forRange()}'s
 * result: the channel's own label, the {@see Page} `x-admin.pager` renders
 * alongside it, and the slice of {@see ChannelVisitRow} a viewer sees.
 */
final readonly class ChannelVisitsPage
{
    /**
     * @param  list<ChannelVisitRow>  $rows
     */
    public function __construct(
        public string $label,
        public Page $page,
        public array $rows,
    ) {}
}
