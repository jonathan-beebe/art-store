<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

/**
 * One funnel {@see Funnel} reads: visitors, then one step per name in the
 * definition it was given, in that order.
 */
final readonly class FunnelView
{
    /**
     * @param  list<FunnelStep>  $steps  visitors first, then one entry per
     *                                   step name in the funnel's definition
     */
    public function __construct(
        public array $steps,
    ) {}
}
