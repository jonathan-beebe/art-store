<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('answers 400 for an unrecognised filter value', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics?{$query}");

    $response->assertStatus(400);
})->with([
    'range not one of 7/30/90' => ['range=14'],
    'range not numeric' => ['range=thirty'],
    'actors not a real kind' => ['actors=blocked'],
]);

it('treats an empty filter value as absent', function (string $field): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics?{$field}=");

    $response->assertOk();
})->with(['range', 'actors', 'q']);

it('defaults to a 30-day range and every actor kind', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics');

    $response->assertOk();
});

it('accepts every well-formed range and actor kind', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics?{$query}");

    $response->assertOk();
})->with([
    '7 days' => ['range=7'],
    '30 days' => ['range=30'],
    '90 days' => ['range=90'],
    'all actors' => ['actors=all'],
    'anonymous actors' => ['actors=anonymous'],
    'verified actors' => ['actors=verified'],
]);
