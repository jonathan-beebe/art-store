<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Models\Customer;
use App\Notifications\OrderShipped;
use Illuminate\Notifications\DatabaseNotification;

$notify = function (Customer $customer, string $orderId): DatabaseNotification {
    $customer->notify(new OrderShipped($orderId, 'Royal Mail', 'RM123456789GB'));

    return $customer->notifications()->firstOrFail();
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

it('offers a link to contact support', function (): void {
    $this->actingAs(Customer::factory()->create(), 'customer');

    $response = $this->get('/account');

    $response->assertSee('href="'.route('shop.support').'"', escape: false);
});

it('lists the notifications of the customer', function () use ($notify): void {
    $shopper = Customer::factory()->create();
    $this->actingAs($shopper, 'customer');
    $notify($shopper, 'ord_00000000000000000000000041');
    $notify(Customer::factory()->create(), 'ord_00000000000000000000000077');

    $response = $this->get('/account');

    $response->assertSee('Order shipped');
    $response->assertSee('Order ord_00000000000000000000000041 shipped with Royal Mail. Tracking number RM123456789GB.');
    $response->assertDontSee('Order ord_00000000000000000000000077');
});

it('marks a notification read', function () use ($notify): void {
    $shopper = Customer::factory()->create();
    $this->actingAs($shopper, 'customer');
    $notification = $notify($shopper, 'ord_00000000000000000000000041');

    $response = $this->post(route('shop.account.notifications.read', $notification));

    $response->assertRedirect(route('shop.account'));
    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('leaves another customer notification alone', function () use ($notify): void {
    $this->actingAs(Customer::factory()->create(), 'customer');
    $notification = $notify(Customer::factory()->create(), 'ord_00000000000000000000000041');

    $response = $this->post(route('shop.account.notifications.read', $notification));

    $response->assertNotFound();
    expect($notification->refresh()->read_at)->toBeNull();
});
