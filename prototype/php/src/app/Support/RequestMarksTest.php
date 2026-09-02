<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\LogRequestStory;
use App\Http\Middleware\NameRequestVisitor;

it('names the same request-id attribute LogRequestStory stamps', function (): void {
    expect(RequestMarks::REQUEST_ID_ATTRIBUTE)->toBe(LogRequestStory::REQUEST_ID_ATTRIBUTE);
});

it('names the same session cookie NameRequestVisitor mints', function (): void {
    expect(RequestMarks::SESSION_COOKIE)->toBe(NameRequestVisitor::SESSION_COOKIE);
});
