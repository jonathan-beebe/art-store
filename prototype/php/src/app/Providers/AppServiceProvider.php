<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\CustomerIdentity;
use App\Support\MagicLinkDelivery\MagicLinkDelivery;
use App\Support\MagicLinkDelivery\MailMagicLinkDelivery;
use App\Support\MagicLinkDelivery\SessionFlashMagicLinkDelivery;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
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
        // The storefront visitor is resolved by middleware rather than signed
        // in on a guard, so `@can` has no user to read there. `@visitorCan`
        // asks the same policies about the visitor the request carries.
        Blade::if('visitorCan', function (string $ability, mixed $subject): bool {
            $visitor = CustomerIdentity::current();

            return $visitor !== null && Gate::forUser($visitor)->allows($ability, $subject);
        });
    }
}
