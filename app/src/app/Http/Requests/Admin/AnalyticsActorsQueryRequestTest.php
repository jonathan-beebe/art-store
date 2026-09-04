<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

it('answers 400 for an unrecognised filter value', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/actors?{$query}");

    $response->assertStatus(400);
})->with([
    'range not one of 7/30/90' => ['range=14'],
    'range not numeric' => ['range=thirty'],
    'sort not a real sort' => ['sort=popular'],
    'actors not a real kind' => ['actors=blocked'],
    'page not numeric' => ['page=one'],
    'page not positive' => ['page=0'],
    'page negative' => ['page=-1'],
]);

it('treats an empty filter value as absent', function (string $field): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/actors?{$field}=");

    $response->assertOk();
})->with(['range', 'sort', 'actors', 'q', 'page']);

it('defaults to a 30-day range, most-active sort, and every actor kind', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/actors');

    $response->assertOk();
});

it('accepts every well-formed range, sort, actor kind, and page', function (string $query): void {
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/actors?{$query}");

    $response->assertOk();
})->with([
    '7 days' => ['range=7'],
    '30 days' => ['range=30'],
    '90 days' => ['range=90'],
    'most active' => ['sort=active'],
    'most recent' => ['sort=recent'],
    'all actors' => ['actors=all'],
    'anonymous actors' => ['actors=anonymous'],
    'verified actors' => ['actors=verified'],
    'page one' => ['page=1'],
    'page far past the end' => ['page=999'],
]);
