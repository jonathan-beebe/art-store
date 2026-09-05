<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\MagicLink;

it('refuses a submission without a usable address', function (string $email): void {
    $response = $this->post('/admin/login', ['email' => $email]);

    $response->assertSessionHasErrors('email');
    expect(MagicLink::count())->toBe(0);
})->with([
    'nothing' => [''],
    'a word' => ['not-an-address'],
]);
