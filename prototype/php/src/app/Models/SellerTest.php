<?php

declare(strict_types=1);

namespace App\Models;

it('resolves a display name', function (array $attributes, string $expected): void {
    expect((new Seller($attributes))->displayName())->toBe($expected);
})->with([
    'shop name wins' => [['email' => 'artist@example.com', 'name' => 'Ada Painter', 'shop_name' => 'Ada Studio'], 'Ada Studio'],
    'name without a shop name' => [['email' => 'artist@example.com', 'name' => 'Ada Painter'], 'Ada Painter'],
    'email alone' => [['email' => 'artist@example.com'], 'artist@example.com'],
]);
