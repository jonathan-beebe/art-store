<?php

declare(strict_types=1);

namespace App\Models;

it('resolves a display name', function (array $attributes, string $expected): void {
    /** @var array<string, mixed> $attributes */
    expect((new Admin($attributes))->displayName())->toBe($expected);
})->with([
    'name wins' => [['email' => 'ops@example.com', 'name' => 'Nia Ops'], 'Nia Ops'],
    'email alone' => [['email' => 'ops@example.com'], 'ops@example.com'],
]);
