<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FulfillmentFlow;
use Illuminate\Auth\Access\Response;

it('lets a seller act on their own flow', function (string $ability): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);

    $response = (new FulfillmentFlowPolicy)->{$ability}($seller, $flow);
    expect($response)->toBeInstanceOf(Response::class);

    /** @var Response $response */
    expect($response->allowed())->toBeTrue();
})->with(['view', 'update']);

it('answers not found for another sellers flow', function (string $ability): void {
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Other Studio')->id]);

    $response = (new FulfillmentFlowPolicy)->{$ability}($this->seller(), $flow);
    expect($response)->toBeInstanceOf(Response::class);

    /** @var Response $response */
    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
})->with(['view', 'update']);
