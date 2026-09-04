<?php

declare(strict_types=1);

namespace App\Seller;

it('builds one link per case, marking the one in force and carrying the round-tripped filters', function (): void {
    $links = NavLinks::for(
        routeName: 'seller.dashboard',
        without: ['storeName' => 'ignored'],
        param: 'range',
        cases: [7, 30, 90],
        label: fn (int $days): string => $days.' days',
        value: fn (int $days): string => (string) $days,
        active: fn (int $days): bool => $days === 30,
    );

    expect(array_map(fn (NavLink $link): string => $link->label, $links))->toBe(['7 days', '30 days', '90 days'])
        ->and(array_map(fn (NavLink $link): bool => $link->active, $links))->toBe([false, true, false])
        ->and($links[2]->href)->toBe(route('seller.dashboard', ['storeName' => 'ignored', 'range' => 90]));
});

it('leaves an empty case list empty', function (): void {
    $links = NavLinks::for(
        routeName: 'seller.dashboard',
        without: [],
        param: 'range',
        cases: [],
        label: fn (int $days): string => (string) $days,
        value: fn (int $days): string => (string) $days,
        active: fn (int $days): bool => false,
    );

    expect($links)->toBe([]);
});
