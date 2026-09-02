<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

/**
 * One bar of an entity page's strip: a day's count for either entity, or —
 * for a flagged actor — one hour of its peak day. `$hot` marks an hourly
 * bar that reached {@see \App\Domain\Analytics\ActorVelocity::THRESHOLD_PER_HOUR},
 * the page's cue to render it in red; every daily bar carries `$hot` false.
 */
final readonly class EntityStripBar
{
    public function __construct(
        public int $height,
        public string $tip,
        public bool $hot,
    ) {}
}
