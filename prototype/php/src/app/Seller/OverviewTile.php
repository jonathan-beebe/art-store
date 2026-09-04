<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Analytics\ChangeDirection;
use App\Domain\Seller\FeedIcon;
use App\Domain\Seller\Sparkline;

/**
 * One of the dashboard's three brand-icon tiles: the figure, how it reads
 * against the range before it, the range's daily line, and the tool the
 * whole tile opens.
 */
final readonly class OverviewTile
{
    public function __construct(
        public FeedIcon $icon,
        public string $label,
        public string $value,
        public string $changeText,
        public ChangeDirection $changeDirection,
        public Sparkline $sparkline,
        public string $footerLabel,
        public string $footerNote,
        public string $href,
    ) {}
}
