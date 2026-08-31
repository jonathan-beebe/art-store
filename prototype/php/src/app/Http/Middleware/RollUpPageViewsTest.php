<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Analytics\PageViewSite;
use App\Models\PageViewCount;
use Illuminate\Support\Facades\Route;

it('rolls a countable GET up into one row', function (): void {
    $this->get('/admin/login');

    $row = PageViewCount::query()->sole();

    expect($row->site)->toBe(PageViewSite::Admin->value)
        ->and($row->path_pattern)->toBe('/admin/login')
        ->and($row->day)->toBe(now()->format('Y-m-d'))
        ->and($row->count)->toBe(1);
});

it('reads the storefront root as its own pattern', function (): void {
    $this->get('/');

    $row = PageViewCount::query()->sole();

    expect($row->site)->toBe(PageViewSite::Shop->value)
        ->and($row->path_pattern)->toBe('/');
});

it('increments the same row on a second hit rather than inserting a new one', function (): void {
    $this->get('/admin/login');
    $this->get('/admin/login');

    $row = PageViewCount::query()->sole();

    expect($row->count)->toBe(2);
});

it('counts nothing for a request that matches no route', function (): void {
    $this->get('/nothing-is-here')->assertNotFound();

    expect(PageViewCount::query()->count())->toBe(0);
});

it('counts nothing for a non-GET request', function (): void {
    $this->post('/admin/login', ['email' => 'not-an-email']);

    expect(PageViewCount::query()->count())->toBe(0);
});

it('counts nothing for a response that is not 2xx', function (): void {
    $this->get('/seller')->assertRedirect();

    expect(PageViewCount::query()->count())->toBe(0);
});

it('counts nothing for a response that is not HTML', function (): void {
    Route::get('/json-test', fn () => response()->json(['ok' => true]));

    $this->getJson('/json-test')->assertOk();

    expect(PageViewCount::query()->count())->toBe(0);
});
