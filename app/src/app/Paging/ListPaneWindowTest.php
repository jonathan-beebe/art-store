<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Paging\ListPaneWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @return Builder<Customer>
 */
function customersNewestFirstForWindowTest(): Builder
{
    return Customer::query()->orderByDesc('created_at')->orderByDesc('id');
}

it('caps a query at the window size, ordering untouched', function (): void {
    Customer::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $window = ListPaneWindow::of(customersNewestFirstForWindowTest());

    expect($window->items)->toHaveCount(ListPaneWindow::SIZE);
    expect($window->total)->toBe(ListPaneWindow::SIZE + 5);
    expect($window->hasMore())->toBeTrue();
});

it('leaves a query alone when it already fits inside the window', function (): void {
    Customer::factory()->count(3)->create();

    $window = ListPaneWindow::of(customersNewestFirstForWindowTest());

    expect($window->items)->toHaveCount(3);
    expect($window->total)->toBe(3);
    expect($window->hasMore())->toBeFalse();
});

it('guarantees the must-include row a place when it sorts outside the window', function (): void {
    $viewed = Customer::factory()->create(['name' => 'Ada Painter', 'created_at' => now()->subDay()]);
    Customer::factory()->count(ListPaneWindow::SIZE + 10)->create();

    $window = ListPaneWindow::of(customersNewestFirstForWindowTest(), $viewed);

    expect($window->items->contains('id', '=', $viewed->id))->toBeTrue();
    // The window plus the one extra fetch that carried the missing row in.
    expect($window->items)->toHaveCount(ListPaneWindow::SIZE + 1);
    expect($window->total)->toBe(ListPaneWindow::SIZE + 11);
    expect($window->hasMore())->toBeTrue();
});

it('does not fetch the must-include row twice when the window already holds it', function (): void {
    $viewed = Customer::factory()->create(['name' => 'Ada Painter']);
    Customer::factory()->count(3)->create();

    $window = ListPaneWindow::of(customersNewestFirstForWindowTest(), $viewed);

    expect($window->items)->toHaveCount(4);
    expect($window->items->filter(fn (Model $customer): bool => $customer->getKey() === $viewed->id))->toHaveCount(1);
});

it('says nothing was left out once the must-include fetch fills the window exactly', function (): void {
    $viewed = Customer::factory()->create(['name' => 'Ada Painter', 'created_at' => now()->subDay()]);
    Customer::factory()->count(ListPaneWindow::SIZE - 1)->create();

    $window = ListPaneWindow::of(customersNewestFirstForWindowTest(), $viewed);

    expect($window->items)->toHaveCount(ListPaneWindow::SIZE);
    expect($window->total)->toBe(ListPaneWindow::SIZE);
    expect($window->hasMore())->toBeFalse();
});
