<?php

declare(strict_types=1);

namespace App\Providers;

use App\Seller\ActivityFeedReader;
use App\Seller\ActivityFeedSource;
use App\Seller\AnalyticsSource;
use App\Seller\FulfillmentSource;
use App\Seller\MessagingSource;
use App\Seller\OrderSource;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * `App\Seller\ActivityFeedReader` takes its sources as a variadic
 * `ActivityFeedSource ...$sources` rather than the four concrete classes
 * by name, so the container needs telling what fills that parameter and
 * in what order — the order a feed's rows tie in: browsing, order,
 * shipping, messages.
 */
final class ActivityFeedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->when(ActivityFeedReader::class)
            ->needs(ActivityFeedSource::class)
            ->give(fn (Application $app): array => [
                $app->make(AnalyticsSource::class),
                $app->make(OrderSource::class),
                $app->make(FulfillmentSource::class),
                $app->make(MessagingSource::class),
            ]);
    }
}
