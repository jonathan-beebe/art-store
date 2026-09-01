<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Money\Money;
use App\Models\Seller;
use App\Notifications\ItemSold;
use App\Notifications\OrderShipped;
use Illuminate\Notifications\DatabaseNotification;

$notify = function (Seller $seller, string $orderId): DatabaseNotification {
    $seller->notify(new ItemSold($orderId, Money::fromCents(9000)));

    return $seller->notifications()->firstOrFail();
};

it('lists the sellers notifications', function () use ($notify): void {
    $seller = $this->seller();
    $notify($seller, 'ord_00000000000000000000000041');

    $response = $this->actingAs($seller, 'seller')->get('/seller/notifications');

    $response->assertOk();
    $response->assertSee('Item sold');
    $response->assertSee('Order ord_00000000000000000000000041 is paid. $90.00 is held until the customer confirms delivery.');
    $response->assertSee('Unread');
});

it('keeps another sellers notifications off the page', function () use ($notify): void {
    $notify($this->seller('Other Studio'), 'ord_00000000000000000000000077');

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/notifications');

    $response->assertDontSee('Order ord_00000000000000000000000077');
});

it('marks a notification read', function () use ($notify): void {
    $seller = $this->seller();
    $notification = $notify($seller, 'ord_00000000000000000000000041');

    $response = $this->actingAs($seller, 'seller')->post("/seller/notifications/{$notification->id}/read");

    $response->assertRedirect(route('seller.notifications.index'));
    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('stops offering to mark a read notification read', function () use ($notify): void {
    $seller = $this->seller();
    $notification = $notify($seller, 'ord_00000000000000000000000041');
    $this->actingAs($seller, 'seller')->post("/seller/notifications/{$notification->id}/read");

    $response = $this->actingAs($seller, 'seller')->get('/seller/notifications');

    $response->assertDontSee('Mark as read');
});

it('answers not found marking a customer notification read even when its id matches the sellers own', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $customer->notify(new OrderShipped('ord_00000000000000000000000041', 'Royal Mail', 'RM123'));
    $notification = $customer->notifications()->firstOrFail();
    // The policy reads `notifiable_type` and `notifiable_id` together — forcing
    // the id half to collide with the seller's own proves the type half is
    // what still refuses it, not an id that never actually matches in practice.
    $notification->forceFill(['notifiable_id' => $seller->id])->save();

    $response = $this->actingAs($seller, 'seller')->post("/seller/notifications/{$notification->id}/read");

    $response->assertNotFound();
    expect($notification->refresh()->read_at)->toBeNull();
});

it('answers not found for a value that is not a notification id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->seller(), 'seller')->post("/seller/notifications/{$id}/read")->assertNotFound();
})->with([
    'another table prefix' => 'ord_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'a notification that does not exist' => 'ntf_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);
