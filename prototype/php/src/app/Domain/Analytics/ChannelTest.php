<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('reads a known medium as its own kind of campaign', function (string $medium, string $expectedLabel): void {
    $channel = Channel::derive('newsletter', $medium, 'sept', null);

    expect($channel->key)->toBe('campaign:sept')
        ->and($channel->label)->toBe("{$expectedLabel} campaign: sept");
})->with([
    'email' => ['email', 'Email'],
    'social' => ['social', 'Social'],
    'paid' => ['paid', 'Paid'],
    'affiliate' => ['affiliate', 'Affiliate'],
]);

it('reads a medium matched case-insensitively', function (): void {
    $channel = Channel::derive(null, 'EMAIL', 'sept', null);

    expect($channel->label)->toBe('Email campaign: sept');
});

it('reads an unrecognized medium as given', function (): void {
    $channel = Channel::derive(null, 'organic', 'sept', null);

    expect($channel->key)->toBe('campaign:sept')
        ->and($channel->label)->toBe('organic campaign: sept');
});

it('names the campaign key from the campaign, falling back to the source, then the medium', function (?string $source, ?string $medium, ?string $campaign, string $expectedKey): void {
    $channel = Channel::derive($source, $medium, $campaign, null);

    expect($channel->key)->toBe($expectedKey);
})->with([
    'campaign given' => ['newsletter', 'email', 'sept', 'campaign:sept'],
    'campaign absent, source given' => ['newsletter', 'email', null, 'campaign:newsletter'],
    'campaign and source absent, medium given' => [null, 'email', null, 'campaign:email'],
]);

it('carries a campaign named with neither a source nor a medium as a bare campaign', function (): void {
    $channel = Channel::derive(null, null, 'sept', null);

    expect($channel->key)->toBe('campaign:sept')
        ->and($channel->label)->toBe('Campaign: sept');
});

it('reads a search engine off the referrer host', function (string $host, string $engine): void {
    $channel = Channel::derive(null, null, null, $host);

    expect($channel->key)->toBe("search:{$engine}")
        ->and($channel->label)->toBe(ucfirst($engine).' search');
})->with([
    'google' => ['www.google.com', 'google'],
    'bing' => ['www.bing.com', 'bing'],
    'duckduckgo' => ['duckduckgo.com', 'duckduckgo'],
    'yahoo' => ['search.yahoo.com', 'yahoo'],
    'ecosia' => ['www.ecosia.org', 'ecosia'],
]);

it('reads a social network off the referrer host', function (string $host, string $network): void {
    $channel = Channel::derive(null, null, null, $host);

    expect($channel->key)->toBe("social:{$network}")
        ->and($channel->label)->toBe(ucfirst($network));
})->with([
    'facebook' => ['www.facebook.com', 'facebook'],
    'instagram' => ['www.instagram.com', 'instagram'],
    'pinterest' => ['www.pinterest.com', 'pinterest'],
    'x.com' => ['x.com', 'x/twitter'],
    'twitter.com' => ['www.twitter.com', 'x/twitter'],
    'tiktok' => ['www.tiktok.com', 'tiktok'],
    'reddit' => ['www.reddit.com', 'reddit'],
]);

it('reads an unrecognized referrer host as a referral, host verbatim', function (): void {
    $channel = Channel::derive(null, null, null, 'www.example.com');

    expect($channel->key)->toBe('referral:example.com')
        ->and($channel->label)->toBe('example.com');
});

it('reads no utm and no referrer as direct', function (): void {
    $channel = Channel::derive(null, null, null, null);

    expect($channel->key)->toBe('direct')
        ->and($channel->label)->toBe('Direct');
});

it('favors utm over the referrer host when both are present', function (): void {
    $channel = Channel::derive('newsletter', 'email', 'sept', 'www.google.com');

    expect($channel->key)->toBe('campaign:sept');
});
