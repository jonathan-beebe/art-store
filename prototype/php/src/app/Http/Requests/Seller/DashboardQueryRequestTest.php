<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Analytics\AnalyticsRange;

it('reads the range the query names', function (int $days): void {
    $this->actingAs($this->seller(), 'seller')
        ->get("/seller?range={$days}")
        ->assertOk()
        ->assertViewHas('range', fn (AnalyticsRange $range): bool => $range->days === $days);
})->with([
    'a week' => [7],
    'a month' => [30],
    'a quarter' => [90],
]);

it('reads an absent or emptied range as thirty days', function (string $query): void {
    $this->actingAs($this->seller(), 'seller')
        ->get('/seller'.$query)
        ->assertOk()
        ->assertViewHas('range', fn (AnalyticsRange $range): bool => $range->days === 30);
})->with([
    'absent' => [''],
    'emptied' => ['?range='],
]);

it('answers a bare 400 for a value the vocabulary does not hold', function (string $range): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller?range='.$range);

    $response->assertStatus(400);
    expect($response->getContent())->toBe('');
})->with([
    'a size outside the three' => ['14'],
    'a word' => ['forever'],
    'a negative number' => ['-7'],
    'an array' => ['[]'],
]);
