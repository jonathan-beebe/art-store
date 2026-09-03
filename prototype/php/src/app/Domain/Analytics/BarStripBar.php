<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * One bar of a rendered strip: a count's scaled height and its tooltip.
 * `$hot` marks a bar that reached {@see ActorVelocity::THRESHOLD_PER_HOUR}
 * on a flagged actor's hourly strip; every daily bar carries `$hot` false.
 */
final readonly class BarStripBar
{
    public function __construct(
        public int $height,
        public string $tip,
        public bool $hot = false,
    ) {}
}
