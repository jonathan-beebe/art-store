<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\ApiKey;

it('requires a name of at most a hundred characters', function (string $name): void {
    $response = $this->actingAs($this->admin(), 'admin')
        ->from(route('admin.api-keys.index'))
        ->post(route('admin.api-keys.store'), ['name' => $name]);

    $response->assertRedirect(route('admin.api-keys.index'))->assertSessionHasErrors('name');
    expect(ApiKey::count())->toBe(0);
})->with([
    'empty' => [''],
    'too long' => [str_repeat('k', 101)],
]);

it('trims the name it accepts', function (): void {
    $this->actingAs($this->admin(), 'admin')->post(route('admin.api-keys.store'), ['name' => '  laptop  ']);

    expect(ApiKey::sole()->name)->toBe('laptop');
});
