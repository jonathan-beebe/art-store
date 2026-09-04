<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

it('offsets and limits a middle page', function (): void {
    $page = Page::of('2', 10, 25);

    expect($page->number)->toBe(2)
        ->and($page->count)->toBe(3)
        ->and($page->offset)->toBe(10)
        ->and($page->limit)->toBe(10)
        ->and($page->isFirst)->toBeFalse()
        ->and($page->isLast)->toBeFalse()
        ->and($page->previousNumber)->toBe(1)
        ->and($page->nextNumber)->toBe(3);
});

it('defaults to the first page when nothing was requested', function (): void {
    $page = Page::of(null, 10, 25);

    expect($page->number)->toBe(1)->and($page->isFirst)->toBeTrue();
});

it('defaults to the first page when the request is not numeric', function (): void {
    $page = Page::of('not-a-number', 10, 25);

    expect($page->number)->toBe(1);
});

it('clamps a page below the first onto the first', function (): void {
    expect(Page::of('0', 10, 25)->number)->toBe(1)
        ->and(Page::of('-3', 10, 25)->number)->toBe(1);
});

it('clamps a page past the last onto the last', function (): void {
    $page = Page::of('99', 10, 25);

    expect($page->number)->toBe(3)->and($page->isLast)->toBeTrue();
});

it('holds one page — the first — even with nothing to show', function (): void {
    $page = Page::of('1', 10, 0);

    expect($page->count)->toBe(1)
        ->and($page->isFirst)->toBeTrue()
        ->and($page->isLast)->toBeTrue();
});

it('refuses a page size below one', function (): void {
    Page::of('1', 0, 10);
})->throws(InvalidArgumentException::class, 'a page holds at least one item, got 0');

it('refuses a negative total count', function (): void {
    Page::of('1', 10, -1);
})->throws(InvalidArgumentException::class, 'a count cannot be negative, got -1');
