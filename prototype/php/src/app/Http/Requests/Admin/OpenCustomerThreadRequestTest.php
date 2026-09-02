<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('refuses a title or a message that is empty or longer than its limit', function (string $field, string $value): void {
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($this->admin(), 'admin')->post("/admin/customers/{$customer->id}/messages", [
        'title' => 'Where is my order?',
        'body' => 'My order never arrived.',
        $field => $value,
    ]);

    $response->assertSessionHasErrors($field);
})->with([
    'empty title' => ['title', ''],
    'title longer than the limit' => ['title', str_repeat('a', 121)],
    'empty body' => ['body', ''],
    'body longer than the limit' => ['body', str_repeat('a', 2001)],
]);

it('refuses an order id that belongs to a different customer', function (): void {
    $customer = $this->verifiedCustomer();
    $other = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->post("/admin/customers/{$customer->id}/messages", [
        'title' => 'Where is my order?',
        'body' => 'My order never arrived.',
        'order' => $other->id,
    ]);

    $response->assertSessionHasErrors('order');
});

it('accepts one of this customers own orders as context', function (): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    $response = $this->actingAs($this->admin(), 'admin')->post("/admin/customers/{$customer->id}/messages", [
        'title' => 'Where is my order?',
        'body' => 'My order never arrived.',
        'order' => $order->id,
    ]);

    $response->assertRedirect();
});

it('reads an emptied order select as no context', function (): void {
    $request = OpenCustomerThreadRequest::create('/admin/customers/1/messages', 'POST', ['title' => 'Support', 'body' => 'Hello.', 'order' => '']);

    expect($request->orderId())->toBeNull();
});

it('reads the title and body the admin typed', function (): void {
    $request = OpenCustomerThreadRequest::create('/admin/customers/1/messages', 'POST', ['title' => 'Where is my order?', 'body' => 'My order never arrived.']);

    expect($request->title()->value)->toBe('Where is my order?')
        ->and($request->body()->value)->toBe('My order never arrived.');
});
