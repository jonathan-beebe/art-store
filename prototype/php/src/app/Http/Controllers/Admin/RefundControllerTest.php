<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\FulfillmentStatus;
use App\Models\Refund;

it('refunds a fulfillment in the admins name', function (): void {
    $admin = $this->admin();
    $fulfillment = $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/fulfillments/{$fulfillment->id}/refund", ['reason' => 'Dispute settled.']);

    $response->assertRedirect(route('admin.fulfillments.show', $fulfillment));
    $response->assertSessionHas('status', 'Refund issued.');
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Refunded)
        ->and(Refund::sole()->issued_by_id)->toBe($admin->id);
});

it('refuses a second refund', function (): void {
    $admin = $this->admin();
    $fulfillment = $this->deliveredFulfillmentFor($this->seller());
    $this->actingAs($admin, 'admin')->post("/admin/fulfillments/{$fulfillment->id}/refund", ['reason' => 'Dispute.']);

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/fulfillments/{$fulfillment->id}/refund", ['reason' => 'Again.']);

    $response->assertSessionHasErrors();
    expect(Refund::count())->toBe(1);
});

it('refuses an invalid reason', function (mixed $reason): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/fulfillments/{$fulfillment->id}/refund", ['reason' => $reason]);

    $response->assertSessionHasErrors('reason');
    expect(Refund::count())->toBe(0);
})->with([
    'missing' => [''],
    'over the character limit' => [str_repeat('a', 501)],
]);

it('refuses to refund a fulfillment on an unpaid order', function (): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));
    $fulfillment = $order->fulfillments()->sole();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/fulfillments/{$fulfillment->id}/refund", ['reason' => 'Dispute.']);

    $response->assertSessionHasErrors();
    expect(Refund::count())->toBe(0);
});

it('answers 404 for an id of the wrong shape', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')
        ->post("/admin/fulfillments/{$id}/refund", ['reason' => 'Dispute.'])
        ->assertNotFound();
})->with([
    'an unknown fulfillment' => ['ful_00000000000000000000000009'],
    'another tables prefix' => ['ord_00000000000000000000000001'],
    'a bare ulid' => ['01J5X3M9A2K8YB7Q4R6T1V0WZE'],
    'nonsense' => ['nonsense'],
]);
