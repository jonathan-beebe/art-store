<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\FulfillmentShipped;
use App\Events\OrderPaid;
use App\Listeners\NotifyCustomerOfShipment;
use App\Listeners\NotifySellerOfSale;
use App\Models\Customer;
use App\Models\Seller;
use App\Policies\NotificationPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

it('names the two recipient sides in the notification morph map', function (): void {
    expect(Relation::morphMap())->toBe([
        'seller' => Seller::class,
        'customer' => Customer::class,
    ]);
});

it('registers the notification policy for the database notification model', function (): void {
    expect(Gate::getPolicyFor(DatabaseNotification::class))->toBeInstanceOf(NotificationPolicy::class);
});

it('listens for the two business-moment events that trigger a notification', function (): void {
    expect(Event::getRawListeners()[OrderPaid::class] ?? [])->toContain(NotifySellerOfSale::class)
        ->and(Event::getRawListeners()[FulfillmentShipped::class] ?? [])->toContain(NotifyCustomerOfShipment::class);
});
