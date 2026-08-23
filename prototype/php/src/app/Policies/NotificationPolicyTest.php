<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification;

it('lets a seller mark their own notification read', function (): void {
    $seller = $this->seller();
    $notification = Notification::create(['seller_id' => $seller->id, 'subject' => 'Item sold', 'body' => 'A print sold.']);

    expect((new NotificationPolicy)->markRead($seller, $notification)->allowed())->toBeTrue();
});

it('lets a customer mark their own notification read', function (): void {
    $customer = $this->verifiedCustomer();
    $notification = Notification::create(['customer_id' => $customer->id, 'subject' => 'Order shipped', 'body' => 'On its way.']);

    expect((new NotificationPolicy)->markRead($customer, $notification)->allowed())->toBeTrue();
});

it('answers not found for another sellers notification', function (): void {
    $notification = Notification::create(['seller_id' => $this->seller('Other Studio')->id, 'subject' => 'Item sold', 'body' => 'A print sold.']);

    $response = (new NotificationPolicy)->markRead($this->seller(), $notification);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('answers not found for another customers notification', function (): void {
    $notification = Notification::create(['customer_id' => $this->verifiedCustomer()->id, 'subject' => 'Order shipped', 'body' => 'On its way.']);

    $response = (new NotificationPolicy)->markRead($this->verifiedCustomer(), $notification);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('reads the recipient column of the site asking, not the row alone', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $notification = Notification::create(['customer_id' => $customer->id, 'subject' => 'Order shipped', 'body' => 'On its way.']);

    expect((new NotificationPolicy)->markRead($seller, $notification)->denied())->toBeTrue();
});
