<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('answers 400 for an unrecognised filter value', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/events/listing.view?{$query}");

    $response->assertStatus(400);
})->with([
    'range not one of 7/30/90' => ['range=14'],
    'by not a real breakdown' => ['by=nonsense'],
    'by not offered by this event' => ['by=pattern'],
]);

it('treats an empty filter value as absent', function (string $field): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/events/listing.view?{$field}=");

    $response->assertOk();
})->with(['range', 'by']);

it('defaults to a 30-day range and the event\'s default breakdown', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/events/listing.view');

    $response->assertOk();
    $response->assertSee('By listing');
});

it('accepts every well-formed range and, for page.view, only the pattern breakdown', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/events/page.view?{$query}");

    $response->assertOk();
})->with([
    '7 days' => ['range=7'],
    '30 days' => ['range=30'],
    '90 days' => ['range=90'],
    'pattern breakdown' => ['by=pattern'],
]);

it('answers 400 for page.view asked to break down by listing or actor', function (string $by): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/events/page.view?by={$by}");

    $response->assertStatus(400);
})->with(['listing', 'actor']);
