<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsVisit;

/**
 * Every request in this file targets `direct`, seeded once per test so the
 * route's own "no visit derives to this key" 404 never masks a validation
 * result the request class is responsible for.
 */
function seedDirectVisitForChannelVisitsRequest(): void
{
    $analytics = app(Analytics::class);
    $analytics->recordVisit(new AnalyticsVisit('sess-direct', now()->toDateTimeImmutable(), '/', null, null, null, null, null, null, null));
    $analytics->flush();
}

it('answers 400 for an unrecognised filter value', function (string $query): void {
    seedDirectVisitForChannelVisitsRequest();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/channels/direct?{$query}");

    $response->assertStatus(400);
})->with([
    'range not one of 7/30/90' => ['range=14'],
    'range not numeric' => ['range=thirty'],
    'page not numeric' => ['page=one'],
    'page not positive' => ['page=0'],
    'page negative' => ['page=-1'],
]);

it('treats an empty filter value as absent', function (string $field): void {
    seedDirectVisitForChannelVisitsRequest();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/channels/direct?{$field}=");

    $response->assertOk();
})->with(['range', 'page']);

it('defaults to a 30-day range and the first page', function (): void {
    seedDirectVisitForChannelVisitsRequest();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/channels/direct');

    $response->assertOk();
});

it('accepts every well-formed range and page', function (string $query): void {
    seedDirectVisitForChannelVisitsRequest();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/channels/direct?{$query}");

    $response->assertOk();
})->with([
    '7 days' => ['range=7'],
    '30 days' => ['range=30'],
    '90 days' => ['range=90'],
    'page one' => ['page=1'],
    'page far past the end' => ['page=999'],
]);
