<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

/**
 * The traffic origin `ActivityPlan` draws for one session — the same shape
 * `App\Analytics\AnalyticsVisit::of()`'s `$utm` argument and `$referrerHost`
 * expect, so `App\Console\Commands\SeedActivity` passes this straight
 * through. Exactly one of a campaign (`utmSource`/`utmMedium`/`utmCampaign`)
 * or a referrer (`referrerHost`) is ever set; a direct visit sets neither —
 * the same precedence `App\Domain\Analytics\Channel::derive()` reads.
 */
final readonly class ChannelPick
{
    public function __construct(
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public ?string $referrerHost,
    ) {}
}
