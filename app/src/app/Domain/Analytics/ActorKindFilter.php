<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * The admin analytics pages' actor-kind segmented control: every actor, or
 * narrowed to one side of {@see \App\Analytics\Admin\ActorIdentity}'s
 * anonymous/verified split.
 */
enum ActorKindFilter: string
{
    case All = 'all';
    case Anonymous = 'anonymous';
    case Verified = 'verified';
}
