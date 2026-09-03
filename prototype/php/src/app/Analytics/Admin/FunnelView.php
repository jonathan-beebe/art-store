<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

/**
 * The whole storefront funnel {@see Funnel} reads: visitors through paid
 * orders, in that order.
 */
final readonly class FunnelView
{
    /**
     * @param  list<FunnelStep>  $steps  exactly seven, in funnel order
     */
    public function __construct(
        public array $steps,
    ) {}
}
