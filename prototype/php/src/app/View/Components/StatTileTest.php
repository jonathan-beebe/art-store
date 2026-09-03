<?php

declare(strict_types=1);

namespace App\View\Components;

/**
 * Every `<p>` class attribute on the five pages, filtered down to the two
 * shapes a stat tile can render: a figure (`tracking-tight` alongside a
 * `text-2xl`/`text-3xl` size — the size the pre-refactor and post-refactor
 * markup use respectively) and a label (`text-sm/6 font-medium`, which nothing
 * else on these five pages sets). `stone-`/`gray-` tokens are normalised to
 * one placeholder first, since the seller pages and admin pages differ only
 * by accent.
 *
 * @return array{0: list<string>, 1: list<string>}
 */
function statTileClasses(string $html): array
{
    preg_match_all('/<p\s([^>]*)>/', $html, $matches);

    $normalize = fn (string $class): string => (string) preg_replace('/\b(stone|gray)-(\d+)\b/', 'ACCENT-$2', $class);

    $figures = [];
    $labels = [];

    foreach ($matches[1] as $attributes) {
        if (! preg_match('/class="([^"]*)"/', $attributes, $classMatch)) {
            continue;
        }

        $class = $classMatch[1];

        if (str_contains($class, 'tracking-tight') && preg_match('/\btext-[23]xl\b/', $class)) {
            $figures[] = $normalize($class);
        }

        if (str_contains($class, 'text-sm/6') && str_contains($class, 'font-medium')) {
            $labels[] = $normalize($class);
        }
    }

    return [$figures, $labels];
}

it('renders one figure class list and one label class list across every stat grid', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();

    $pages = [
        (string) $this->actingAs($admin, 'admin')->get('/admin')->getContent(),
        (string) $this->actingAs($admin, 'admin')->get('/admin/ledger')->getContent(),
        (string) $this->actingAs($admin, 'admin')->get('/admin/accounting')->getContent(),
        (string) $this->actingAs($seller, 'seller')->get('/seller')->getContent(),
        (string) $this->actingAs($seller, 'seller')->get('/seller/earnings')->getContent(),
    ];

    $figures = [];
    $labels = [];

    foreach ($pages as $html) {
        [$pageFigures, $pageLabels] = statTileClasses($html);
        array_push($figures, ...$pageFigures);
        array_push($labels, ...$pageLabels);
    }

    // Fourteen tiles across the five pages: six on the dashboard's money
    // row plus its Traffic tile, four on the ledger, six on accounting,
    // four on the seller dashboard, four on earnings — 25 in all.
    expect($figures)->toHaveCount(25)
        ->and($labels)->toHaveCount(25);

    expect(array_values(array_unique($figures)))->toHaveCount(1);
    expect(array_values(array_unique($labels)))->toHaveCount(1);
});

it('caps every figure at text-2xl, the size that leaves room for the widest total to stay on one line', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();

    $html = (string) $this->actingAs($admin, 'admin')->get('/admin')->getContent();
    [$figures] = statTileClasses($html);

    expect($figures)->not->toBeEmpty();

    foreach ($figures as $class) {
        expect($class)->toContain('text-2xl')
            ->and($class)->not->toContain('text-3xl');
    }
});

it('pins every figure to the bottom of its cell, so a wrapped label grows upward without dropping the row off its baseline', function (): void {
    $admin = $this->admin();

    $html = (string) $this->actingAs($admin, 'admin')->get('/admin')->getContent();
    [$figures] = statTileClasses($html);

    expect($figures)->not->toBeEmpty();

    foreach ($figures as $class) {
        expect($class)->toContain('mt-auto');
    }
});
