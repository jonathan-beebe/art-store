<?php

declare(strict_types=1);

namespace App\Analytics;

use App\Http\Middleware\LogRequestStory;
use App\Http\Middleware\NameRequestVisitor;
use Illuminate\Http\Request;

it('reads nothing when the container holds no request LogRequestStory has stamped', function (): void {
    $facts = RequestFacts::current();

    expect($facts->ip)->toBeNull()
        ->and($facts->sessionId)->toBeNull()
        ->and($facts->requestId)->toBeNull();
});

it('reads the ip, session, and request id off the request LogRequestStory stamped', function (): void {
    $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.9']);
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    $request->cookies->set(NameRequestVisitor::SESSION_COOKIE, 'ses_01J00000000000000000000ABC');
    $this->app->instance('request', $request);

    $facts = RequestFacts::current();

    expect($facts->ip)->toBe('203.0.113.9')
        ->and($facts->sessionId)->toBe('ses_01J00000000000000000000ABC')
        ->and($facts->requestId)->toBe('req_01J00000000000000000000ABC');
});

it('leaves the session null when the request carries no sid cookie yet', function (): void {
    $request = Request::create('/');
    $request->attributes->set(LogRequestStory::REQUEST_ID_ATTRIBUTE, 'req_01J00000000000000000000ABC');
    $this->app->instance('request', $request);

    expect(RequestFacts::current()->sessionId)->toBeNull();
});

it('treats an unstamped request the same as none — the console kernel\'s synthetic one, for instance', function (): void {
    $request = Request::create('/');
    $request->cookies->set(NameRequestVisitor::SESSION_COOKIE, 'ses_01J00000000000000000000ABC');
    $this->app->instance('request', $request);

    $facts = RequestFacts::current();

    expect($facts->ip)->toBeNull()
        ->and($facts->sessionId)->toBeNull()
        ->and($facts->requestId)->toBeNull();
});

it('builds every field directly, for a test with no request behind it', function (): void {
    $facts = RequestFacts::of('203.0.113.9', 'ses_01J00000000000000000000ABC', 'req_01J00000000000000000000ABC');

    expect($facts->ip)->toBe('203.0.113.9')
        ->and($facts->sessionId)->toBe('ses_01J00000000000000000000ABC')
        ->and($facts->requestId)->toBe('req_01J00000000000000000000ABC');
});
