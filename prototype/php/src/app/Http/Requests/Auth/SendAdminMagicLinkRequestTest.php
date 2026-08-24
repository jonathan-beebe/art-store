<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\Admin;
use App\Models\MagicLink;

it('refuses a submission without a usable address', function (string $email): void {
    $response = $this->post('/admin/login', ['email' => $email]);

    $response->assertSessionHasErrors('email');
    expect(MagicLink::count())->toBe(0);
})->with([
    'nothing' => [''],
    'a word' => ['not-an-address'],
]);

it('admits an address with an admin row', function (): void {
    Admin::factory()->create(['email' => 'ops@example.com']);

    $request = SendAdminMagicLinkRequest::create('/admin/login', 'POST', ['email' => 'Ops@Example.com']);

    expect($request->admits())->toBeTrue();
});

it('does not admit an address with no admin row', function (): void {
    $request = SendAdminMagicLinkRequest::create('/admin/login', 'POST', ['email' => 'nobody@example.com']);

    expect($request->admits())->toBeFalse();
});
