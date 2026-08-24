<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identifiers\PrefixedId;
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

it('opens and closes a request that matches no route', function (): void {
    $log = CapturedStory::capture();

    $response = $this->get('/nothing-is-here');

    $response->assertNotFound();

    expect($log->line('http.request', 'will')['msg'])->toBe('GET /nothing-is-here')
        ->and($log->line('http.request', 'did')['data'])->toBe(['status' => 404]);
});

it('opens and closes a request the forgery guard refuses', function (): void {
    Route::middleware('web')->post('/forgery-check', fn (): string => 'never reached');

    // The guard stands aside while the application knows it is under test,
    // so a refusal only happens with that knowledge taken away.
    $this->app->instance('env', 'local');

    $log = CapturedStory::capture();

    $response = $this->post('/forgery-check');

    $response->assertStatus(419);

    expect($log->line('http.request', 'will')['msg'])->toBe('POST /forgery-check')
        ->and($log->line('http.request', 'did')['data'])->toBe(['status' => 419]);
});

it('echoes the request id it minted', function (): void {
    $log = CapturedStory::capture();

    $response = $this->get('/');

    $requestId = $log->line('http.request', 'will')['request_id'];

    expect($requestId)->toBeString()
        ->and(PrefixedId::parse('req', is_string($requestId) ? $requestId : ''))->not->toBeNull();

    $response->assertHeader(LogRequestStory::REQUEST_ID_HEADER, $requestId);
});

it('echoes the request id on the response to a request that broke', function (): void {
    Route::middleware('web')->get('/boom', fn () => throw new RuntimeException('the page broke'));

    $log = CapturedStory::capture();

    $response = $this->get('/boom');

    $response->assertStatus(500)
        ->assertHeader(LogRequestStory::REQUEST_ID_HEADER, $log->line('http.request', 'will')['request_id']);
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
    'a newline on the end' => ["trace42\n"],
    'empty' => [''],
    'past sixty-four characters' => [str_repeat('a', 65)],
]);

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
