<?php

declare(strict_types=1);

use App\Domain\Listings\ListingStatus;
use App\Models\Category;
use App\Support\Shop\CategoryBrowse;

it('offers nothing before any browsable root category exists', function (): void {
    expect(CategoryBrowse::forStorefront())->toBe([]);
});

it('counts a root category across itself and its descendants', function (): void {
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $rings = Category::factory()->childOf($jewelry, 'Rings')->create();
    $seller = $this->seller();
    $this->listing($seller, ['category_id' => $jewelry->id]);
    $this->listing($seller, ['category_id' => $rings->id]);
    $this->listing($seller, ['category_id' => $rings->id, 'status' => ListingStatus::Draft]);

    $browse = CategoryBrowse::forStorefront();

    expect($browse)->toHaveCount(1)
        ->and($browse[0]['category']->id)->toBe($jewelry->id)
        ->and($browse[0]['count'])->toBe(2);
});

it('orders root categories by name and leaves out a child category', function (): void {
    $apparel = Category::factory()->create(['name' => 'Apparel', 'path' => '/apparel/']);
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    Category::factory()->childOf($jewelry, 'Rings')->create();

    $browse = CategoryBrowse::forStorefront();

    expect(array_map(fn (array $entry): string => $entry['category']->id, $browse))->toBe([$apparel->id, $jewelry->id]);
});

it('leaves out a root category marked not browsable', function (): void {
    Category::factory()->hidden()->create(['name' => 'Hidden Room', 'path' => '/hidden-room/']);

    expect(CategoryBrowse::forStorefront())->toBe([]);
});
