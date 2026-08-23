<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Notifications\Notify;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Models\Customer;
use App\Models\Notification;

$notify = function (Customer $customer, int $orderId): Notification {
    return app(Notify::class)(
        RecipientType::Customer,
        $customer->id,
        NotificationMessage::orderShipped($orderId, 'Royal Mail', 'RM123456789GB'),
    );
};

it('shows the verified address', function (): void {
    $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');

    $response = $this->get('/account');

    $response->assertOk();
    $response->assertSee('shopper@example.com');
});

it('offers a sign out form', function (): void {
    $this->actingAs(Customer::factory()->create(), 'customer');

    $response = $this->get('/account');

    $response->assertSee('action="'.route('auth.customer.logout').'"', escape: false);
});

it('lists the notifications of the customer', function () use ($notify): void {
    $shopper = Customer::factory()->create();
    $this->actingAs($shopper, 'customer');
    $notify($shopper, 41);
    $notify(Customer::factory()->create(), 77);

    $response = $this->get('/account');

    $response->assertSee('Order shipped');
    $response->assertSee('Order #41 shipped with Royal Mail.');
    $response->assertDontSee('Order #77');
});

it('marks a notification read', function () use ($notify): void {
    $shopper = Customer::factory()->create();
    $this->actingAs($shopper, 'customer');
    $notification = $notify($shopper, 41);

    $response = $this->post(route('shop.account.notifications.read', $notification));

    $response->assertRedirect(route('shop.account'));
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('leaves another customer notification alone', function () use ($notify): void {
    $this->actingAs(Customer::factory()->create(), 'customer');
    $notification = $notify(Customer::factory()->create(), 41);

    $response = $this->post(route('shop.account.notifications.read', $notification));

    $response->assertNotFound();
    expect($notification->fresh()->read_at)->toBeNull();
});
