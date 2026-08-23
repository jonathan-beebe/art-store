<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\MagicLinkDelivery\MagicLinkDelivery;
use App\Support\MagicLinkDelivery\MailMagicLinkDelivery;
use App\Support\MagicLinkDelivery\SessionFlashMagicLinkDelivery;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MagicLinkDelivery::class, function (Application $app): MagicLinkDelivery {
            $channel = config('magic_links.delivery');

            if ($channel === 'session') {
                return new SessionFlashMagicLinkDelivery($app->make('session.store'));
            }

            return match ($channel) {
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
