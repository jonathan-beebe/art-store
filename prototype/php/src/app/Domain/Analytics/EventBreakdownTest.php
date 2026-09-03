<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('offers only a pattern breakdown for page.view', function (): void {
    expect(EventBreakdown::allowedFor('page.view'))->toBe([EventBreakdown::Pattern])
        ->and(EventBreakdown::defaultFor('page.view'))->toBe(EventBreakdown::Pattern);
});

it('offers listing and actor breakdowns for every other event name, defaulting to listing', function (): void {
    foreach (AnalyticsEventName::cases() as $case) {
        expect(EventBreakdown::allowedFor($case->value))->toBe([EventBreakdown::Listing, EventBreakdown::Actor])
            ->and(EventBreakdown::defaultFor($case->value))->toBe(EventBreakdown::Listing);
    }
});

it('names the event page\'s breakdown heading and table column', function (): void {
    expect(EventBreakdown::Listing->heading())->toBe('By listing')
        ->and(EventBreakdown::Listing->columnLabel())->toBe('Listing')
        ->and(EventBreakdown::Actor->heading())->toBe('By actor')
        ->and(EventBreakdown::Actor->columnLabel())->toBe('Actor')
        ->and(EventBreakdown::Pattern->heading())->toBe('By pattern')
        ->and(EventBreakdown::Pattern->columnLabel())->toBe('Pattern');
});
