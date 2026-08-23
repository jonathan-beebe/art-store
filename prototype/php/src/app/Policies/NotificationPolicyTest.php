<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Money\Money;
use App\Models\Customer;
use App\Models\Seller;
use App\Notifications\ItemSold;
use App\Notifications\OrderShipped;
use Illuminate\Notifications\DatabaseNotification;

$soldTo = function (Seller $seller): DatabaseNotification {
    $seller->notify(new ItemSold(4, Money::fromCents(9000)));

    return $seller->notifications()->sole();
};

$shippedTo = function (Customer $customer): DatabaseNotification {
    $customer->notify(new OrderShipped(4, 'USPS', '9400111899'));

    return $customer->notifications()->sole();
};

it('lets a seller mark their own notification read', function () use ($soldTo): void {
    $seller = $this->seller();

    expect((new NotificationPolicy)->markRead($seller, $soldTo($seller))->allowed())->toBeTrue();
});

it('lets a customer mark their own notification read', function () use ($shippedTo): void {
    $customer = $this->verifiedCustomer();

    expect((new NotificationPolicy)->markRead($customer, $shippedTo($customer))->allowed())->toBeTrue();
});

it('answers not found for another sellers notification', function () use ($soldTo): void {
    $notification = $soldTo($this->seller('Other Studio'));

    $response = (new NotificationPolicy)->markRead($this->seller(), $notification);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('answers not found for another customers notification', function () use ($shippedTo): void {
    $notification = $shippedTo($this->verifiedCustomer());

    $response = (new NotificationPolicy)->markRead($this->verifiedCustomer(), $notification);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('reads the recipient type as well as the id, which the two sites number apart', function () use ($shippedTo): void {
    $customer = $this->verifiedCustomer();
    $seller = $this->seller();
    $notification = $shippedTo($customer);

    expect($seller->id)->toBe($customer->id)
        ->and((new NotificationPolicy)->markRead($seller, $notification)->denied())->toBeTrue();
});
