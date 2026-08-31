<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identifiers\PrefixedId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\CapturedStory;

it('opens and closes the request with a line of its own', function (): void {
    $log = CapturedStory::capture();

    $this->get('/');

    $will = $log->line('http.request', 'will');
    $did = $log->line('http.request', 'did');

    /** @var array<string, mixed> $didData */
    $didData = $did['data'];
    /** @var array<string, mixed> $db */
    $db = $didData['db'];

    expect($will['msg'])->toBe('GET /')
        ->and($will['data'])->toBe(['method' => 'GET', 'path' => '/'])
        ->and($did['msg'])->toBe('GET / 200')
        ->and($didData['status'])->toBe(200)
        ->and($db['queries'])->toBeInt()
        ->and($db['total_ms'])->toBeNumeric()
        ->and($did['duration_ms'])->toBeInt();
});

it('carries the query parameters on the opening line, and omits the key without them', function (): void {
    $log = CapturedStory::capture();

    $this->get('/?medium=ceramic&q=cup');

    expect($log->line('http.request', 'will')['data'])->toBe([
        'method' => 'GET',
        'path' => '/',
        'query' => ['medium' => 'ceramic', 'q' => 'cup'],
    ]);
});

it('redacts a query parameter shaped like an email address', function (): void {
    $log = CapturedStory::capture();

    $this->get('/?q=harry%40hogwarts.example');

    expect($log->line('http.request', 'will')['data'])->toBe([
        'method' => 'GET',
        'path' => '/',
        'query' => ['q' => '[redacted]'],
    ]);
});

it('opens and closes a request that matches no route', function (): void {
    $log = CapturedStory::capture();

    $response = $this->get('/nothing-is-here');

    $response->assertNotFound();

    expect($log->line('http.request', 'will')['msg'])->toBe('GET /nothing-is-here')
        ->and($log->line('http.request', 'did')['data'])->toBe(['status' => 404, 'db' => ['queries' => 0, 'total_ms' => 0]]);
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
        ->and($log->line('http.request', 'did')['data'])->toBe(['status' => 419, 'db' => ['queries' => 0, 'total_ms' => 0]]);
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

it('reports zero database work on the closing line when no query ran', function (): void {
    // No `web` group: a session-backed request would carry its own
    // reads/writes, which is exactly the noise this route avoids.
    Route::get('/no-query', fn (): string => 'ok');

    $log = CapturedStory::capture();

    $this->get('/no-query');

    /** @var array<string, mixed> $data */
    $data = $log->line('http.request', 'did')['data'];

    expect($data['db'])->toBe(['queries' => 0, 'total_ms' => 0]);
});

it('counts and sums the queries a request ran onto its closing line', function (): void {
    Route::get('/query-test', function (): string {
        DB::select('select 1');
        DB::select('select 1');

        return 'ok';
    });

    $log = CapturedStory::capture();

    $this->get('/query-test');

    /** @var array<string, mixed> $data */
    $data = $log->line('http.request', 'did')['data'];
    /** @var array<string, mixed> $db */
    $db = $data['db'];

    expect($db['queries'])->toBe(2)
        ->and($db['total_ms'])->toBeNumeric()
        ->and($db['total_ms'])->toBeGreaterThanOrEqual(0);
});

it("does not leak one request's query tally into the next", function (): void {
    Route::get('/query-test', function (): string {
        DB::select('select 1');
        DB::select('select 1');

        return 'ok';
    });
    Route::get('/no-query', fn (): string => 'ok');

    $log = CapturedStory::capture();

    $this->get('/query-test');
    $this->get('/no-query');

    $lines = $log->linesFor('http.request');
    $secondDid = array_values(array_filter($lines, fn (array $line): bool => $line['phase'] === 'did'))[1];

    /** @var array<string, mixed> $data */
    $data = $secondDid['data'];

    expect($data['db'])->toBe(['queries' => 0, 'total_ms' => 0]);
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
