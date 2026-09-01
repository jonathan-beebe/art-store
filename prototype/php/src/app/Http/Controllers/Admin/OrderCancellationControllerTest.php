<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\OrderStatus;

it('cancels an unpaid order', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->post("/admin/orders/{$order->id}/cancel");

    $response->assertRedirect(route('admin.orders.show', $order));
    $response->assertSessionHas('status', 'Order cancelled.');
    expect($order->fresh()?->status)->toBe(OrderStatus::Cancelled);
});

it('refuses to cancel an order that has been paid', function (): void {
    $order = $this->paidOrderWithTwoSellers();

    $response = $this->actingAs($this->admin(), 'admin')->post("/admin/orders/{$order->id}/cancel");

    $response->assertSessionHasErrors();
    expect($order->fresh()?->status)->toBe(OrderStatus::Paid);
});

it('answers 404 for an id of the wrong shape', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')->post("/admin/orders/{$id}/cancel")->assertNotFound();
})->with([
    'an unknown order' => ['ord_00000000000000000000000009'],
    'another tables prefix' => ['ful_00000000000000000000000001'],
    'a bare ulid' => ['01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'nonsense' => ['nonsense'],
]);
