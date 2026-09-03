<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

it('carries at least 120 people', function (): void {
    expect(count(HogwartsRoster::people()))->toBeGreaterThanOrEqual(120);
});

it('gives every person a name and an example.com email', function (): void {
    foreach (HogwartsRoster::people() as $person) {
        expect($person['name'])->not->toBe('')
            ->and($person['email'])->toEndWith('@example.com');
    }
});

it('never repeats an email across two people', function (): void {
    $emails = array_column(HogwartsRoster::people(), 'email');

    expect(array_unique($emails))->toHaveCount(count($emails));
});

it('never names an email already seeded by make fresh', function (): void {
    $seeded = [
        'molly@example.com', 'dean@example.com', 'sybill@example.com',
        'colin@example.com', 'neville@example.com', 'luna@example.com',
        'hermione@example.com',
    ];

    expect(array_intersect(array_column(HogwartsRoster::people(), 'email'), $seeded))->toBe([]);
});

it('returns the same order on every call, so an index addresses the same person', function (): void {
    expect(HogwartsRoster::people())->toBe(HogwartsRoster::people());
});
