<?php

declare(strict_types=1);

use App\Providers\ActivityFeedServiceProvider;
use App\Providers\AnalyticsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\LoggingServiceProvider;
use App\Providers\LogStoreServiceProvider;

return [
    AppServiceProvider::class,
    LoggingServiceProvider::class,
    LogStoreServiceProvider::class,
    AnalyticsServiceProvider::class,
    ActivityFeedServiceProvider::class,
];
