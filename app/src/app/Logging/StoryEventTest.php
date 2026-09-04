<?php

declare(strict_types=1);

namespace App\Logging;

it('opens a unit of work for every event but the http request wrapper', function (): void {
    expect(StoryEvent::HttpRequest->opensUnitOfWork())->toBeFalse()
        ->and(StoryEvent::OrderPlace->opensUnitOfWork())->toBeTrue();
});

it('logs a ledger write at debug, off the story', function (): void {
    expect(StoryEvent::LedgerWrite->level())->toBe(StoryLevel::Debug);
});

it('logs a slow query at warn', function (): void {
    expect(StoryEvent::QueryExceed->level())->toBe(StoryLevel::Warn);
});

it('logs any other event at info by default', function (): void {
    expect(StoryEvent::OrderPlace->level())->toBe(StoryLevel::Info);
});

it('refuses a listing view at debug, off the story', function (): void {
    expect(StoryEvent::ListingView->refusalLevel())->toBe(StoryLevel::Debug);
});

it('refuses a rate limit trip at warn', function (): void {
    expect(StoryEvent::RateLimitExceed->refusalLevel())->toBe(StoryLevel::Warn);
});

it('refuses any other event at info by default', function (): void {
    expect(StoryEvent::OrderPlace->refusalLevel())->toBe(StoryLevel::Info);
});
