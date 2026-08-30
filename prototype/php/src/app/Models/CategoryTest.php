<?php

declare(strict_types=1);

namespace App\Models;

it('nests under a parent and lists its children', function (): void {
    $parent = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $child = Category::factory()->childOf($parent, 'Rings')->create();

    expect($child->parent()->first()?->id)->toBe($parent->id)
        ->and($parent->children()->pluck('id')->all())->toBe([$child->id])
        ->and($child->path)->toBe('/jewelry/rings/');
});

it('is browsable by default and hidden in the hidden state', function (): void {
    expect(Category::factory()->create()->browsable)->toBeTrue()
        ->and(Category::factory()->hidden()->create()->browsable)->toBeFalse();
});

it('grants properties and lists the listings placed in it', function (): void {
    $category = Category::factory()->create();
    CategoryProperty::factory()->create(['category_id' => $category->id]);
    $listing = $this->listing($this->seller(), ['category_id' => $category->id]);

    expect($category->categoryProperties()->count())->toBe(1)
        ->and($category->listings()->pluck('id')->all())->toBe([$listing->id]);
});

it('strips the path down to the /browse/{categoryPath} segment', function (): void {
    $root = Category::factory()->create(['path' => '/jewelry/']);
    $child = Category::factory()->childOf($root, 'Rings')->create();

    expect($root->browsePath())->toBe('jewelry')
        ->and($child->browsePath())->toBe('jewelry/rings');
});
