<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\MagicLink;

it('refuses a submission without a usable address', function (string $route, string $email): void {
    $response = $this->post($route, ['email' => $email]);

    $response->assertSessionHasErrors('email');
    expect(MagicLink::count())->toBe(0);
})->with([
    'a customer sending nothing' => ['/login', ''],
    'a customer sending a word' => ['/login', 'not-an-address'],
    'a seller sending nothing' => ['/seller/login', ''],
    'a seller sending a word' => ['/seller/login', 'not-an-address'],
]);

it('carries a local destination onto the link', function (): void {
    $this->post('/login', ['email' => 'shopper@example.com', 'redirect_to' => '/checkout']);

    expect(MagicLink::sole()->redirect_to)->toBe('/checkout');
});

it('drops a destination that leaves this site', function (string $destination): void {
    $this->post('/login', ['email' => 'shopper@example.com', 'redirect_to' => $destination]);

    expect(MagicLink::sole()->redirect_to)->toBeNull();
})->with([
    'another host' => ['https://elsewhere.test/checkout'],
    'a protocol-relative host' => ['//elsewhere.test/checkout'],
    'a field the form left empty' => [''],
]);

it('reads the address the visitor typed', function (): void {
    $request = SendMagicLinkRequest::create('/login', 'POST', ['email' => 'shopper@example.com']);

    expect($request->email())->toBe('shopper@example.com')
        ->and($request->redirectTo())->toBeNull();
});
