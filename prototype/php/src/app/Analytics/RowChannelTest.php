<?php

declare(strict_types=1);

namespace App\Analytics;

it('derives the channel a raw attribution row carries', function (): void {
    $row = (object) [
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
        'utm_campaign' => 'sept',
        'referrer_host' => null,
    ];

    expect(RowChannel::of($row)->key)->toBe('campaign:sept');
});

it('falls back to the referrer host when the row carries no utm', function (): void {
    $row = (object) [
        'utm_source' => null,
        'utm_medium' => null,
        'utm_campaign' => null,
        'referrer_host' => 'www.google.com',
    ];

    expect(RowChannel::of($row)->key)->toBe('search:google');
});

it('reads direct off a row carrying neither utm nor a referrer host', function (): void {
    $row = (object) [
        'utm_source' => null,
        'utm_medium' => null,
        'utm_campaign' => null,
        'referrer_host' => null,
    ];

    expect(RowChannel::of($row)->key)->toBe('direct');
});
