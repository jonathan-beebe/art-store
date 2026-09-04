<?php

declare(strict_types=1);

namespace App\Domain\Seller;

it('defaults to list', function (): void {
    expect(ListingView::default())->toBe(ListingView::List);
});

it('shows a sort control on table and grid, not on list', function (ListingView $view, bool $expected): void {
    expect($view->showsSort())->toBe($expected);
})->with([
    'list has no sort control' => [ListingView::List, false],
    'table sorts' => [ListingView::Table, true],
    'grid sorts' => [ListingView::Grid, true],
]);

it('labels each view', function (ListingView $view, string $expected): void {
    expect($view->label())->toBe($expected);
})->with([
    [ListingView::List, 'List'],
    [ListingView::Table, 'Table'],
    [ListingView::Grid, 'Grid'],
]);
