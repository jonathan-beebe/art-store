<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\Auth\ActorType;
use DateTimeInterface;

/**
 * Read from `resolved_at` rather than stored twice: a thread is resolved
 * exactly when that column is set.
 */
enum ConversationStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public static function of(?DateTimeInterface $resolvedAt): self
    {
        return $resolvedAt === null ? self::Open : self::Resolved;
    }

    /**
     * The one rule `PostMessage` applies: a post from an actor the kind does
     * not let resolve reopens a resolved thread ("actually, one more
     * thing"); a post from the side that could have resolved it leaves the
     * status alone, resolved or not.
     */
    public function afterPostBy(ActorType $actor, ConversationKind $kind): self
    {
        if ($this === self::Open) {
            return self::Open;
        }

        return $kind->resolvableBy($actor) ? self::Resolved : self::Open;
    }
}
