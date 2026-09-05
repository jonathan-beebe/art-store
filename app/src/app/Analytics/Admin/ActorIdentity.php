<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\ActorKind;
use App\Models\Customer;

/**
 * How an actor reads on the admin analytics pages: `anonymous` when the
 * customer row carries no verified email, `verified` otherwise, paired
 * with what to call them — their verified address, or "never signed in".
 * {@see ActorLeaderboard} and {@see AnalyticsJump} both read a customer
 * this way, so the two never label the same actor differently.
 */
final readonly class ActorIdentity
{
    private function __construct(
        public ActorKind $kind,
        public string $who,
    ) {}

    public static function of(Customer $customer): self
    {
        $verified = $customer->isVerified();

        return new self(
            $verified ? ActorKind::Verified : ActorKind::Anonymous,
            $verified && $customer->email !== null ? $customer->email : 'never signed in',
        );
    }
}
