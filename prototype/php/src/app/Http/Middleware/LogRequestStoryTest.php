<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identifiers\PrefixedId;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Seller;
use App\Support\CustomerIdentity;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\CapturedStory;

it('opens and closes the request with a line of its own', function (): void {
    $log = CapturedStory::capture();

    $this->get('/');

    $will = $log->line('http.request', 'will');
    $did = $log->line('http.request', 'did');

    expect($will['msg'])->toBe('GET /')
        ->and($will['data'])->toBe(['method' => 'GET', 'path' => '/'])
        ->and($did['msg'])->toBe('GET / 200')
        ->and($did['data'])->toBe(['status' => 200])
        ->and($did['duration_ms'])->toBeInt();
});

it('echoes the request id it minted', function (): void {
    $log = CapturedStory::capture();

    $response = $this->get('/');

    $requestId = $log->line('http.request', 'will')['request_id'];

    expect($requestId)->toBeString()
        ->and(PrefixedId::parse('req', is_string($requestId) ? $requestId : ''))->not->toBeNull();

    $response->assertHeader(LogRequestStory::REQUEST_ID_HEADER, $requestId);
});

it('honours a request id the caller sent, in the one shape it admits', function (): void {
    $log = CapturedStory::capture();

    $this->withHeader(LogRequestStory::REQUEST_ID_HEADER, 'trace-42_ABC')->get('/')
        ->assertHeader(LogRequestStory::REQUEST_ID_HEADER, 'trace-42_ABC');

    expect($log->line('http.request', 'will')['request_id'])->toBe('trace-42_ABC');
});

it('mints its own request id rather than echoing one of any other shape', function (string $given): void {
    $log = CapturedStory::capture();

    $this->withHeader(LogRequestStory::REQUEST_ID_HEADER, $given)->get('/');

    expect($log->line('http.request', 'will')['request_id'])->not->toBe($given);
})->with([
    'a space' => ['trace 42'],
    'a slash' => ['../../etc/passwd'],
    'a newline' => ["trace\n42"],
    'empty' => [''],
    'past sixty-four characters' => [str_repeat('a', 65)],
]);

it('mints the session cookie on the first response a browser gets', function (): void {
    $log = CapturedStory::capture();

    $response = $this->get('/');

    $sessionId = $log->line('http.request', 'will')['session_id'];

    expect(PrefixedId::parse('ses', is_string($sessionId) ? $sessionId : ''))->not->toBeNull();

    $response->assertCookie(LogRequestStory::SESSION_COOKIE, $sessionId);
    expect($response->getCookie(LogRequestStory::SESSION_COOKIE)?->getExpiresTime())
        ->toBeGreaterThan(now()->addDays(360)->getTimestamp());
});

it('keeps the session the browser already holds', function (): void {
    $log = CapturedStory::capture();

    $held = 'ses_01J00000000000000000000ABC';

    $this->withCookie(LogRequestStory::SESSION_COOKIE, $held)->get('/');

    expect($log->line('http.request', 'will')['session_id'])->toBe($held);
});

it('mints a fresh session when the cookie holds something that is not one', function (): void {
    $log = CapturedStory::capture();

    $this->withCookie(LogRequestStory::SESSION_COOKIE, 'cus_01J00000000000000000000ABC')->get('/');

    expect($log->line('http.request', 'will')['session_id'])->not->toBe('cus_01J00000000000000000000ABC');
});

it('carries one session through signing in and signing out', function (): void {
    $held = 'ses_01J00000000000000000000ABC';
    $customer = Customer::factory()->create();

    $log = CapturedStory::capture();

    $this->withCookie(LogRequestStory::SESSION_COOKIE, $held)
        ->actingAs($customer, 'customer')
        ->get('/account');

    $this->withCookie(LogRequestStory::SESSION_COOKIE, $held)->post('/logout');

    expect(array_values(array_unique($log->values('session_id', 'http.request'))))->toBe([$held]);
});

it('names the actor behind the request', function (): void {
    $seller = Seller::factory()->create();

    $log = CapturedStory::capture();

    $this->actingAs($seller, 'seller')->get('/seller');

    expect($log->line('http.request', 'will'))
        ->toHaveKey('actor_type', 'seller')
        ->toHaveKey('actor_id', $seller->id);
});

it('names an admin behind the request', function (): void {
    $admin = Admin::factory()->create();

    $log = CapturedStory::capture();

    $this->actingAs($admin, 'admin')->get('/admin');

    expect($log->line('http.request', 'will'))->toHaveKey('actor_type', 'admin');
});

it('names an anonymous visitor by the identity cookie they already hold', function (): void {
    $visitor = Customer::factory()->anonymous()->create();

    $log = CapturedStory::capture();

    $this->withCookie(CustomerIdentity::COOKIE, (string) $visitor->id)->get('/');

    expect($log->line('http.request', 'will'))
        ->toHaveKey('actor_type', 'customer')
        ->toHaveKey('actor_id', $visitor->id);
});

it('names the visitor a first-time browser is given, from the line after it is created', function (): void {
    $log = CapturedStory::capture();

    $this->get('/');

    expect($log->line('http.request', 'will'))->not->toHaveKey('actor_id')
        ->and($log->line('http.request', 'did'))->toHaveKey('actor_type', 'customer');
});

it('keeps a magic-link token out of the path it logs', function (): void {
    $token = str_repeat('a1b2', 20);

    $log = CapturedStory::capture();

    $this->get("/auth/magic/{$token}");

    expect($log->line('http.request', 'will')['data'])->toBe([
        'method' => 'GET',
        'path' => '/auth/magic/{token}',
    ])
        ->and($log->raw())->not->toContain($token);
});

it('says the request failed and lets the exception through', function (): void {
    Route::middleware('web')->get('/boom', fn () => throw new RuntimeException('the page broke'));

    $log = CapturedStory::capture();

    $this->withoutExceptionHandling();

    expect(fn () => $this->get('/boom'))->toThrow(RuntimeException::class, 'the page broke');

    $line = $log->line('http.request', 'failed');

    expect($line['level'])->toBe('error')
        ->and($line['error'])->toBe(['type' => RuntimeException::class, 'message' => 'the page broke'])
        ->and($line['duration_ms'])->toBeInt();
});
