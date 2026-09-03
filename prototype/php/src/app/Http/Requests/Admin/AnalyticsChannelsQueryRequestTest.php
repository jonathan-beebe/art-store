<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('answers 400 for an unrecognised filter value', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/channels?{$query}");

    $response->assertStatus(400);
})->with([
    'range not one of 7/30/90' => ['range=14'],
    'range not numeric' => ['range=thirty'],
]);

it('treats an empty filter value as absent', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/channels?range=');

    $response->assertOk();
});

it('defaults to a 30-day range', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/channels');

    $response->assertOk();
});

it('accepts every well-formed range', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/channels?{$query}");

    $response->assertOk();
})->with([
    '7 days' => ['range=7'],
    '30 days' => ['range=30'],
    '90 days' => ['range=90'],
]);
