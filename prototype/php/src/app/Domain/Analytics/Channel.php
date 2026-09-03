<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * The origin a visit's later events attribute to: a named campaign, a
 * search engine, a social network, a referring site, or direct. `key` is
 * the string {@see \App\Analytics\Admin\ChannelTable} groups by
 * (`campaign:sept`, `search:google`, `social:instagram`,
 * `referral:example.com`, `direct`); `label` is what a reader sees.
 */
final readonly class Channel
{
    private const string DIRECT_KEY = 'direct';

    private const string DIRECT_LABEL = 'Direct';

    /** @var array<string, string> utm_medium value, lowercased, to the label its campaign channel carries */
    private const array MEDIUM_LABELS = [
        'email' => 'Email',
        'social' => 'Social',
        'paid' => 'Paid',
        'affiliate' => 'Affiliate',
    ];

    /** @var list<string> referrer host substrings read as a search engine */
    private const array SEARCH_ENGINES = ['google', 'bing', 'duckduckgo', 'yahoo', 'ecosia'];

    /** @var array<string, string> referrer host substring to the social network name its channel carries */
    private const array SOCIAL_NETWORKS = [
        'facebook' => 'facebook',
        'instagram' => 'instagram',
        'pinterest' => 'pinterest',
        'x.com' => 'x/twitter',
        'twitter.com' => 'x/twitter',
        'tiktok' => 'tiktok',
        'reddit' => 'reddit',
    ];

    private function __construct(
        public string $key,
        public string $label,
    ) {}

    /**
     * A campaign named by `$utmSource`/`$utmMedium`/`$utmCampaign` wins
     * over `$referrerHost`, which wins over direct — the same precedence
     * a visit's own capture already favors utm parameters ahead of the
     * `Referer` header for. `$referrerHost` is a foreign host only, the
     * shape {@see \App\Analytics\AnalyticsVisit::fromRequest()} already
     * stores.
     */
    public static function derive(
        ?string $utmSource,
        ?string $utmMedium,
        ?string $utmCampaign,
        ?string $referrerHost,
    ): self {
        if ($utmSource !== null || $utmMedium !== null || $utmCampaign !== null) {
            return self::fromCampaign($utmSource, $utmMedium, $utmCampaign);
        }

        if ($referrerHost !== null) {
            return self::fromReferrer($referrerHost);
        }

        return new self(self::DIRECT_KEY, self::DIRECT_LABEL);
    }

    /**
     * `$utmCampaign`, falling back to the source then the medium, is what
     * tells two campaigns of the same kind apart, so it is what the key
     * groups by. The medium names the kind of campaign (email, social,
     * paid, affiliate, or the medium as given, when it names none of
     * those); the source stands in for the medium when the medium is
     * absent, and a campaign named with neither carries no kind at all.
     */
    private static function fromCampaign(?string $utmSource, ?string $utmMedium, ?string $utmCampaign): self
    {
        $name = $utmCampaign ?? $utmSource ?? $utmMedium ?? 'campaign';

        if ($utmMedium === null && $utmSource === null) {
            return new self("campaign:{$name}", "Campaign: {$name}");
        }

        $kind = $utmMedium ?? $utmSource;
        $kindLabel = self::MEDIUM_LABELS[strtolower($kind)] ?? $kind;

        return new self("campaign:{$name}", "{$kindLabel} campaign: {$name}");
    }

    private static function fromReferrer(string $host): self
    {
        $bareHost = self::stripWww(strtolower($host));

        foreach (self::SEARCH_ENGINES as $engine) {
            if (str_contains($bareHost, $engine)) {
                return new self("search:{$engine}", ucfirst($engine).' search');
            }
        }

        foreach (self::SOCIAL_NETWORKS as $needle => $network) {
            if (str_contains($bareHost, $needle)) {
                return new self("social:{$network}", ucfirst($network));
            }
        }

        return new self("referral:{$bareHost}", $bareHost);
    }

    private static function stripWww(string $host): string
    {
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
