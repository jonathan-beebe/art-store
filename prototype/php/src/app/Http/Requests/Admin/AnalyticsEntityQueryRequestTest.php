<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('answers 400 for an unrecognised filter value', function (string $query): void {
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/actors/{$customer->id}?{$query}");

    $response->assertStatus(400);
})->with([
    'range not one of 7/30/90' => ['range=14'],
    'event not a real event name' => ['event=nonsense'],
]);

it('treats an empty filter value as absent', function (string $field): void {
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/actors/{$customer->id}?{$field}=");

    $response->assertOk();
})->with(['range', 'event']);

it('treats event=all the same as no event filter', function (): void {
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/actors/{$customer->id}?event=all");

    $response->assertOk();
});

it('defaults to a 30-day range with no event filter', function (): void {
    $customer = $this->verifiedCustomer();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/actors/{$customer->id}");

    $response->assertOk();
});
