<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Funnel;

it('lists every funnel in position order, with its step chain', function (): void {
    Funnel::factory()->create(['name' => 'Second', 'slug' => 'second', 'position' => 2, 'steps' => ['checkout.open', 'order.pay']]);
    Funnel::factory()->create(['name' => 'First', 'slug' => 'first', 'position' => 1, 'steps' => ['listing.view', 'listing.cart_add']]);

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.funnels.index'));

    $response->assertOk();
    $response->assertSeeInOrder(['First', 'Listing views → Cart adds', 'Second', 'Checkouts opened → Orders paid']);
});

it('says so when there are no funnels', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.funnels.index'));

    $response->assertOk();
    $response->assertSee('No funnels yet.');
});

it('renders the new-funnel form with two blank step rows', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.funnels.create'));

    $response->assertOk();
    $response->assertSee('New funnel');
    expect(substr_count((string) $response->getContent(), 'Choose an event'))->toBe(2);
});

it('creates a funnel from valid steps and redirects to the index', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Gift Shopping',
        'steps' => ['listing.view', 'listing.cart_add', 'order.place'],
        'op' => 'save',
    ]);

    $response->assertRedirect(route('admin.funnels.index'));

    $funnel = Funnel::query()->where('slug', 'gift-shopping')->sole();
    expect($funnel->name)->toBe('Gift Shopping')
        ->and($funnel->steps)->toBe(['listing.view', 'listing.cart_add', 'order.place']);
});

it('derives the slug from the name when creating', function (): void {
    $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Gift Shopping',
        'steps' => ['listing.view', 'listing.cart_add'],
    ]);

    expect(Funnel::query()->where('name', 'Gift Shopping')->sole()->slug)->toBe('gift-shopping');
});

it('positions a new funnel after every existing one', function (): void {
    Funnel::factory()->create(['position' => 5]);

    $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Gift Shopping',
        'steps' => ['listing.view', 'listing.cart_add'],
    ]);

    expect(Funnel::query()->where('name', 'Gift Shopping')->sole()->position)->toBe(6);
});

it('refuses to save a funnel with an unknown step name', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Gift Shopping',
        'steps' => ['listing.view', 'listing.teleport'],
    ]);

    $response->assertSessionHasErrors('steps');
    expect(Funnel::count())->toBe(0);
});

it('refuses to save a funnel with a repeated step name', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Gift Shopping',
        'steps' => ['listing.view', 'listing.view'],
    ]);

    $response->assertSessionHasErrors('steps');
    expect(Funnel::count())->toBe(0);
});

it('refuses to save a funnel with fewer than two steps', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Gift Shopping',
        'steps' => ['listing.view'],
    ]);

    $response->assertSessionHasErrors('steps');
    expect(Funnel::count())->toBe(0);
});

it('refuses a slug already used by another funnel', function (): void {
    Funnel::factory()->create(['slug' => 'storefront']);

    $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Storefront Two',
        'slug' => 'storefront',
        'steps' => ['listing.view', 'listing.cart_add'],
    ]);

    $response->assertSessionHasErrors('slug');
    expect(Funnel::count())->toBe(1);
});

it('adds a blank row and re-renders the create form without saving', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Gift Shopping',
        'steps' => ['listing.view'],
        'op' => 'add_step',
    ]);

    $response->assertOk();
    $response->assertSee('Gift Shopping', escape: false);
    expect(substr_count((string) $response->getContent(), 'Choose an event'))->toBe(2);
    expect(Funnel::count())->toBe(0);
});

it('removes a row and re-renders the create form without saving', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Gift Shopping',
        'steps' => ['listing.view', 'listing.cart_add', 'order.place'],
        'op' => 'remove_step:1',
    ]);

    $response->assertOk();
    $response->assertDontSee('value="listing.cart_add" selected', escape: false);
    expect(Funnel::count())->toBe(0);
});

it('moves a row up and re-renders the create form without saving', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->post(route('admin.funnels.store'), [
        'name' => 'Gift Shopping',
        'steps' => ['listing.view', 'listing.cart_add'],
        'op' => 'move_up:1',
    ]);

    $response->assertOk();
    $response->assertSeeInOrder(['id="funnel-step-0"', 'Cart adds', 'id="funnel-step-1"', 'Listing views'], escape: false);
    expect(Funnel::count())->toBe(0);
});

it('renders the edit form with the funnel\'s own name and steps', function (): void {
    $funnel = Funnel::factory()->create(['name' => 'Gift Shopping', 'steps' => ['listing.view', 'listing.cart_add']]);

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.funnels.edit', $funnel));

    $response->assertOk();
    $response->assertSee('Gift Shopping');
});

it('updates a funnel\'s name and steps and redirects to the index', function (): void {
    $funnel = Funnel::factory()->create(['name' => 'Gift Shopping', 'steps' => ['listing.view', 'listing.cart_add']]);

    $response = $this->actingAs($this->admin(), 'admin')->put(route('admin.funnels.update', $funnel), [
        'name' => 'Holiday Shopping',
        'steps' => ['listing.view', 'order.place'],
        'op' => 'save',
    ]);

    $response->assertRedirect(route('admin.funnels.index'));

    $funnel->refresh();
    expect($funnel->name)->toBe('Holiday Shopping')
        ->and($funnel->steps)->toBe(['listing.view', 'order.place']);
});

it('lets an update keep its own slug', function (): void {
    $funnel = Funnel::factory()->create(['slug' => 'gift-shopping', 'steps' => ['listing.view', 'listing.cart_add']]);

    $response = $this->actingAs($this->admin(), 'admin')->put(route('admin.funnels.update', $funnel), [
        'name' => 'Gift Shopping',
        'slug' => 'gift-shopping',
        'steps' => ['listing.view', 'listing.cart_add'],
    ]);

    $response->assertSessionHasNoErrors();
});

it('refuses to update a funnel to another funnel\'s slug', function (): void {
    Funnel::factory()->create(['slug' => 'taken']);
    $funnel = Funnel::factory()->create(['slug' => 'gift-shopping', 'steps' => ['listing.view', 'listing.cart_add']]);

    $response = $this->actingAs($this->admin(), 'admin')->put(route('admin.funnels.update', $funnel), [
        'name' => 'Gift Shopping',
        'slug' => 'taken',
        'steps' => ['listing.view', 'listing.cart_add'],
    ]);

    $response->assertSessionHasErrors('slug');
});

it('adds a row and re-renders the edit form without saving', function (): void {
    $funnel = Funnel::factory()->create(['steps' => ['listing.view', 'listing.cart_add']]);

    $response = $this->actingAs($this->admin(), 'admin')->put(route('admin.funnels.update', $funnel), [
        'name' => $funnel->name,
        'steps' => ['listing.view', 'listing.cart_add'],
        'op' => 'add_step',
    ]);

    $response->assertOk();
    expect(substr_count((string) $response->getContent(), 'Choose an event'))->toBe(3);
    expect($funnel->refresh()->steps)->toBe(['listing.view', 'listing.cart_add']);
});

it('deletes a funnel and redirects to the index', function (): void {
    $funnel = Funnel::factory()->create();

    $response = $this->actingAs($this->admin(), 'admin')->delete(route('admin.funnels.destroy', $funnel));

    $response->assertRedirect(route('admin.funnels.index'));
    expect(Funnel::query()->find($funnel->id))->toBeNull();
});
