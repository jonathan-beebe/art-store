<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\Response;

it('lets a customer act on their own order', function (string $ability): void {
    $customer = $this->verifiedCustomer();
    $order = $this->orderFor($customer, $this->listing($this->seller()));

    $response = (new OrderPolicy)->{$ability}($customer, $order);
    expect($response)->toBeInstanceOf(Response::class);

    /** @var Response $response */
    expect($response->allowed())->toBeTrue();
})->with(['view', 'pay']);

it('answers not found for another customers order', function (string $ability): void {
    $order = $this->orderFor($this->verifiedCustomer(), $this->listing($this->seller()));

    $response = (new OrderPolicy)->{$ability}($this->verifiedCustomer(), $order);
    expect($response)->toBeInstanceOf(Response::class);

    /** @var Response $response */
    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
})->with(['view', 'pay']);
