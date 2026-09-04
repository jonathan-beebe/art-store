<?php

declare(strict_types=1);

namespace App\Providers;

use App\Seller\ActivityFeedReader;
use App\Seller\AnalyticsSource;
use App\Seller\FulfillmentSource;
use App\Seller\MessagingSource;
use App\Seller\OrderSource;
use ReflectionProperty;

it('binds the four sources to ActivityFeedReader, in the order a feed ties rows in', function (): void {
    $reader = app(ActivityFeedReader::class);

    /** @var list<object> $sources */
    $sources = (new ReflectionProperty(ActivityFeedReader::class, 'sources'))->getValue($reader);

    expect(array_map(get_class(...), $sources))->toBe([
        AnalyticsSource::class,
        OrderSource::class,
        FulfillmentSource::class,
        MessagingSource::class,
    ]);
});
