<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Whether an actor behind an event carries a verified email or never
 * signed in — {@see \App\Analytics\Admin\ActorIdentity::of()} reads a
 * customer into one of these two.
 */
enum ActorKind: string
{
    case Verified = 'verified';
    case Anonymous = 'anonymous';

    /**
     * The word an admin page prints for this kind, kept apart from
     * `$value` so a wording change never touches storage.
     */
    public function label(): string
    {
        return $this->value;
    }
}
