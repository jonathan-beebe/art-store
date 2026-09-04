<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Orders\FulfillmentStatus;
use App\Models\Refund;

it('refuses a refund with no reason on the record', function (array $form, string $field): void {
    $fulfillment = $this->deliveredFulfillmentFor($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/fulfillments/{$fulfillment->id}/refund", $form);

    $response->assertSessionHasErrors($field);
    expect($fulfillment->refresh()->status)->toBe(FulfillmentStatus::Delivered)
        ->and(Refund::count())->toBe(0);
})->with([
    'no reason' => [['reason' => ''], 'reason'],
    'a reason longer than the column' => [['reason' => str_repeat('a', 501)], 'reason'],
]);

it('reads the reason the admin typed', function (): void {
    $request = RefundFulfillmentRequest::create('/admin/fulfillments/1/refund', 'POST', ['reason' => 'Dispute settled.']);

    expect($request->reason())->toBe('Dispute settled.');
});
