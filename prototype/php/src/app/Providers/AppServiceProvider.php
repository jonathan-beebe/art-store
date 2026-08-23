<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notifications\RecipientType;
use App\Events\FulfillmentShipped;
use App\Events\OrderPaid;
use App\Listeners\NotifyCustomerOfShipment;
use App\Listeners\NotifySellerOfSale;
use App\Models\Customer;
use App\Models\Seller;
use App\Policies\NotificationPolicy;
use App\Support\CustomerIdentity;
use App\View\Composers\ShopLayoutComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // A lazy load, a discarded attribute, or a read of a column the query
        // never selected is a defect anywhere but production, where the page
        // still has to render.
        Model::shouldBeStrict(! $this->app->isProduction());

        // A notification names its recipient by one of these two words rather
        // than a class string, so `notifications.notifiable_type` reads the
        // way the domain talks and survives a class moving.
        Relation::enforceMorphMap([
            RecipientType::Seller->value => Seller::class,
            RecipientType::Customer->value => Customer::class,
        ]);

        // The inbox rows are the framework's model, so the policy that guards
        // them is registered rather than discovered.
        Gate::policy(DatabaseNotification::class, NotificationPolicy::class);

        // What each business moment tells someone. Both listeners implement
        // ShouldHandleEventsAfterCommit, so nothing is sent for a transaction
        // that rolls back.
        Event::listen(OrderPaid::class, NotifySellerOfSale::class);
        Event::listen(FulfillmentShipped::class, NotifyCustomerOfShipment::class);

        // The header counts belong to the layout that renders them, so every
        // storefront page gets them without its controller passing them along.
        View::composer('layouts.shop', ShopLayoutComposer::class);

        // The storefront visitor is resolved by middleware rather than signed
        // in on a guard, so `@can` has no user to read there. `@visitorCan`
        // asks the same policies about the visitor the request carries.
        Blade::if('visitorCan', function (string $ability, mixed $subject): bool {
            $visitor = CustomerIdentity::current();

            return $visitor !== null && Gate::forUser($visitor)->allows($ability, $subject);
        });
    }
}
