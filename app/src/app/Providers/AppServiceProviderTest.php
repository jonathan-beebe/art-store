<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\FulfillmentShipped;
use App\Events\MessagePosted;
use App\Events\OrderCancelled;
use App\Events\OrderPaid;
use App\Events\RefundIssued;
use App\Listeners\NotifyCustomerOfShipment;
use App\Listeners\NotifyOfCancellation;
use App\Listeners\NotifyOfMessage;
use App\Listeners\NotifyOfRefund;
use App\Listeners\NotifySellerOfSale;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Seller;
use App\Notifications\OrderShipped;
use App\Policies\NotificationPolicy;
use App\Shop\CustomerIdentity;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

it('names the three actor sides in the notification and message morph map', function (): void {
    expect(Relation::morphMap())->toBe([
        'seller' => Seller::class,
        'customer' => Customer::class,
        'admin' => Admin::class,
    ]);
});

it('registers the notification policy for the database notification model', function (): void {
    expect(Gate::getPolicyFor(DatabaseNotification::class))->toBeInstanceOf(NotificationPolicy::class);
});

it('listens for the business-moment events that trigger a notification', function (): void {
    expect(Event::getRawListeners()[OrderPaid::class] ?? [])->toContain(NotifySellerOfSale::class)
        ->and(Event::getRawListeners()[FulfillmentShipped::class] ?? [])->toContain(NotifyCustomerOfShipment::class)
        ->and(Event::getRawListeners()[OrderCancelled::class] ?? [])->toContain(NotifyOfCancellation::class)
        ->and(Event::getRawListeners()[RefundIssued::class] ?? [])->toContain(NotifyOfRefund::class)
        ->and(Event::getRawListeners()[MessagePosted::class] ?? [])->toContain(NotifyOfMessage::class);
});

it('lets @visitorCan through for a signed-in visitor the gate allows', function (): void {
    $customer = $this->verifiedCustomer();
    $customer->notify(new OrderShipped('ord_00000000000000000000000004', 'USPS', '9400111899'));
    $notification = $customer->notifications()->sole();

    $request = Request::create('/');
    app()->instance('request', $request);
    CustomerIdentity::attachTo($request, $customer);

    $html = Blade::render(
        '@visitorCan(\'markRead\', $notification) yes @endvisitorCan',
        ['notification' => $notification],
    );

    expect(trim($html))->toBe('yes');
});

it('blocks @visitorCan when there is no visitor', function (): void {
    app()->instance('request', Request::create('/'));

    $html = Blade::render(
        '@visitorCan(\'markRead\', $notification) yes @endvisitorCan',
        ['notification' => null],
    );

    expect(trim($html))->toBe('');
});
