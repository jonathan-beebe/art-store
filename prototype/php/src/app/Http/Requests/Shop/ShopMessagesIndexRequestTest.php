<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

it('defaults filter to all and status to open', function (): void {
    $this->arriveAs($this->verifiedCustomer());

    $response = $this->get('/messages');

    $response->assertOk();
});

it('answers 400 for an unrecognised filter', function (): void {
    $this->arriveAs($this->verifiedCustomer());

    $response = $this->get('/messages?filter=starred');

    $response->assertStatus(400);
});

it('answers 400 for an unrecognised status', function (): void {
    $this->arriveAs($this->verifiedCustomer());

    $response = $this->get('/messages?status=archived');

    $response->assertStatus(400);
});

it('reads an empty filter value as absent', function (): void {
    $this->arriveAs($this->verifiedCustomer());

    $response = $this->get('/messages?filter=&status=');

    $response->assertOk();
});
