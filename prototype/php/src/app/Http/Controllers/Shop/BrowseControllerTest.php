<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Domain\Listings\ListingStatus;
use App\Models\Category;

it('lists for-sale listings placed directly in the category', function (): void {
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $seller = $this->seller();
    $ring = $this->listing($seller, ['title' => 'Silver Band', 'category_id' => $jewelry->id]);
    $unrelated = $this->listing($seller, ['title' => 'Oak Table']);

    $response = $this->get('/browse/jewelry');

    $response->assertOk();
    $response->assertSee($ring->title);
    $response->assertDontSee($unrelated->title);
});

it('includes listings placed in a descendant category', function (): void {
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $rings = Category::factory()->childOf($jewelry, 'Rings')->create();
    $listing = $this->listing($this->seller(), ['title' => 'Gold Band', 'category_id' => $rings->id]);

    $response = $this->get('/browse/jewelry');

    $response->assertOk();
    $response->assertSee($listing->title);
});

it('narrows to the child category alone at its own path', function (): void {
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $rings = Category::factory()->childOf($jewelry, 'Rings')->create();
    $seller = $this->seller();
    $ring = $this->listing($seller, ['title' => 'Gold Band', 'category_id' => $rings->id]);
    $necklace = $this->listing($seller, ['title' => 'Pearl Necklace', 'category_id' => $jewelry->id]);

    $response = $this->get('/browse/jewelry/rings');

    $response->assertOk();
    $response->assertSee($ring->title);
    $response->assertDontSee($necklace->title);
});

it('leaves out listings that are not for sale', function (): void {
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $draft = $this->listing($this->seller(), ['title' => 'Unfinished Ring', 'category_id' => $jewelry->id, 'status' => ListingStatus::Draft]);

    $response = $this->get('/browse/jewelry');

    $response->assertOk();
    $response->assertDontSee($draft->title);
});

it('titles the page with the category name', function (): void {
    Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);

    $response = $this->get('/browse/jewelry');

    $response->assertSee('<title>Jewelry — Art Store</title>', escape: false);
});

it('links its child categories', function (): void {
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    Category::factory()->childOf($jewelry, 'Rings')->create();

    $response = $this->get('/browse/jewelry');

    $response->assertSee('href="'.route('shop.browse', ['categoryPath' => 'jewelry/rings']).'"', escape: false);
    $response->assertSee('Rings');
});

it('404s an unknown category path', function (): void {
    $response = $this->get('/browse/nope');

    $response->assertNotFound();
});

it('404s a category marked not browsable', function (): void {
    Category::factory()->hidden()->create(['name' => 'Hidden Room', 'path' => '/hidden-room/']);

    $response = $this->get('/browse/hidden-room');

    $response->assertNotFound();
});

it('paginates at twelve listings', function (): void {
    $jewelry = Category::factory()->create(['name' => 'Jewelry', 'path' => '/jewelry/']);
    $seller = $this->seller();
    for ($index = 1; $index <= 13; $index++) {
        $this->listing($seller, [
            'title' => sprintf('Study No %02d', $index),
            'price_cents' => 1000 + $index,
            'category_id' => $jewelry->id,
        ]);
    }

    $first = $this->get('/browse/jewelry');
    $second = $this->get('/browse/jewelry?page=2');

    $first->assertSee('Study No 13');
    $first->assertDontSee('Study No 01');
    $second->assertSee('Study No 01');
});
