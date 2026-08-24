<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('counts a successful HTML GET', function (): void {
    expect(PageViewCountability::isCountable('GET', 200, 'text/html'))->toBeTrue();
});

it('reads the method without regard to case', function (): void {
    expect(PageViewCountability::isCountable('get', 200, 'text/html'))->toBeTrue();
});

it('still counts a content type carrying a charset parameter', function (): void {
    expect(PageViewCountability::isCountable('GET', 200, 'text/html; charset=UTF-8'))->toBeTrue();
});

it('does not count a non-GET request', function (): void {
    expect(PageViewCountability::isCountable('POST', 200, 'text/html'))->toBeFalse();
});

it('does not count a status below 200', function (): void {
    expect(PageViewCountability::isCountable('GET', 101, 'text/html'))->toBeFalse();
});

it('does not count a status at or above 300', function (): void {
    expect(PageViewCountability::isCountable('GET', 404, 'text/html'))->toBeFalse();
});

it('does not count a missing content type', function (): void {
    expect(PageViewCountability::isCountable('GET', 200, null))->toBeFalse();
});

it('does not count a non-HTML content type', function (): void {
    expect(PageViewCountability::isCountable('GET', 200, 'application/json'))->toBeFalse();
});
