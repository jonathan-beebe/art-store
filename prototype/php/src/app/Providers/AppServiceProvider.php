<?php

namespace App\Providers;

use App\Support\MagicLinkDelivery\MagicLinkDelivery;
use App\Support\MagicLinkDelivery\MailMagicLinkDelivery;
use App\Support\MagicLinkDelivery\SessionFlashMagicLinkDelivery;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MagicLinkDelivery::class, function ($app): MagicLinkDelivery {
            $channel = config('magic_links.delivery');

            return match ($channel) {
                'session' => new SessionFlashMagicLinkDelivery($app['session.store']),
                'mail' => new MailMagicLinkDelivery,
                default => throw new InvalidArgumentException("Unknown magic link delivery [{$channel}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
