<?php

declare(strict_types=1);

namespace App\Models;

it('nests a child\'s path under its parent', function (): void {
    $parent = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $child = Category::factory()->childOf($parent, 'Rings')->create();

    expect($child->path)->toBe('/jewelry/rings/');
});

it('is browsable by default and hidden in the hidden state', function (): void {
    expect(Category::factory()->create()->browsable)->toBeTrue()
        ->and(Category::factory()->hidden()->create()->browsable)->toBeFalse();
});

it('strips the path down to the /browse/{categoryPath} segment', function (): void {
    $root = Category::factory()->create(['path' => '/jewelry/']);
    $child = Category::factory()->childOf($root, 'Rings')->create();

    expect($root->browsePath())->toBe('jewelry')
        ->and($child->browsePath())->toBe('jewelry/rings');
});
