<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Domain\Listings\ListingStatus;

it('refuses a question longer than the message limit', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn']);

    $response = $this->post('/art/harbour-at-dawn/questions', ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
});

it('answers not found for an archived listing before it validates the form', function (): void {
    $this->visitor();
    $this->listing($this->seller(), ['slug' => 'harbour-at-dawn', 'status' => ListingStatus::Archived]);

    $response = $this->post('/art/harbour-at-dawn/questions', []);

    $response->assertNotFound();
    $response->assertSessionHasNoErrors();
});

it('reads the question the visitor typed', function (): void {
    $request = AskSellerRequest::create('/art/harbour-at-dawn/questions', 'POST', ['body' => 'Does this ship framed?']);

    expect($request->body()->value)->toBe('Does this ship framed?');
});
