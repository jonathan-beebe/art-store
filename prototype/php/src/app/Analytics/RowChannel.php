<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Domain\Analytics\Channel;
use stdClass;

/**
 * The {@see Channel} a raw `analytics_visits` (or `analytics_visits`-joined)
 * row derives to — shared by every reader that selects `utm_source`,
 * `utm_medium`, `utm_campaign`, and `referrer_host` off a query result and
 * needs {@see Channel::derive()}'s precedence applied to them.
 */
final class RowChannel
{
    private function __construct() {} // @codeCoverageIgnore

    public static function of(stdClass $row): Channel
    {
        /** @var string|null $utmSource */
        $utmSource = $row->utm_source;
        /** @var string|null $utmMedium */
        $utmMedium = $row->utm_medium;
        /** @var string|null $utmCampaign */
        $utmCampaign = $row->utm_campaign;
        /** @var string|null $referrerHost */
        $referrerHost = $row->referrer_host;

        return Channel::derive($utmSource, $utmMedium, $utmCampaign, $referrerHost);
    }
}
