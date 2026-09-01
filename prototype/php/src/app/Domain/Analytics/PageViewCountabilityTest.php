<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('reads whether a request counts as a page view', function (string $method, int $status, ?string $contentType, bool $expected): void {
    expect(PageViewCountability::isCountable($method, $status, $contentType))->toBe($expected);
})->with([
    'a successful HTML GET counts' => ['GET', 200, 'text/html', true],
    'the method reads without regard to case' => ['get', 200, 'text/html', true],
    'a content type carrying a charset parameter still counts' => ['GET', 200, 'text/html; charset=UTF-8', true],
    'a non-GET request does not count' => ['POST', 200, 'text/html', false],
    'a status below 200 does not count' => ['GET', 101, 'text/html', false],
    'a status at or above 300 does not count' => ['GET', 404, 'text/html', false],
    'the boundary just under 300 counts' => ['GET', 299, 'text/html', true],
    'the boundary at 300 does not count' => ['GET', 300, 'text/html', false],
    'a missing content type does not count' => ['GET', 200, null, false],
    'a non-HTML content type does not count' => ['GET', 200, 'application/json', false],
]);
