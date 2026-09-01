<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PDO;
use Tests\CapturedStory;

it('logs a query over the threshold with its source, time, text, and the threshold itself', function (): void {
    config(['log_store.slow_query_ms' => 50]);
    $log = CapturedStory::capture();

    SlowQueryWatch::listen(executedQuery('select * from listings', [], 62.5));

    $line = $log->line('query.exceed', 'did');
    /** @var array<string, mixed> $data */
    $data = $line['data'];

    expect($line['level'])->toBe('warn')
        ->and($line['msg'])->toStartWith('⚠️ ')
        ->and($data['source'])->toMatch('#^app/Support/SlowQueryWatchTest\.php:\d+$#')
        ->and($data['duration_ms'])->toBe(62.5)
        ->and($data['sql'])->toBe('select * from listings')
        ->and($data['threshold_ms'])->toBe(50);
});

it('logs nothing for a query under the threshold', function (): void {
    config(['log_store.slow_query_ms' => 50]);
    $log = CapturedStory::capture();

    SlowQueryWatch::listen(executedQuery('select 1', [], 10.0));

    expect($log->linesFor('query.exceed'))->toBeEmpty();
});

it('logs nothing for a query exactly at the threshold', function (): void {
    config(['log_store.slow_query_ms' => 50]);
    $log = CapturedStory::capture();

    SlowQueryWatch::listen(executedQuery('select 1', [], 50.0));

    expect($log->linesFor('query.exceed'))->toBeEmpty();
});

it('disables the line for "off"', function (): void {
    config(['log_store.slow_query_ms' => null]);
    $log = CapturedStory::capture();

    SlowQueryWatch::listen(executedQuery('select 1', [], 500.0));

    expect($log->linesFor('query.exceed'))->toBeEmpty();
});

it('never carries a binding into the logged line', function (): void {
    config(['log_store.slow_query_ms' => 1]);
    $log = CapturedStory::capture();

    SlowQueryWatch::listen(executedQuery('select * from customers where email = ?', ['ada@hogwarts.example'], 5.0));

    /** @var array<string, mixed> $data */
    $data = $log->line('query.exceed', 'did')['data'];

    expect($data['sql'])->toBe('select * from customers where email = ?')
        ->and($log->raw())->not->toContain('ada@hogwarts.example');
});

it('names the request route as the source and leaves the query count and total on the did line unchanged', function (): void {
    Route::get('/slow-query-test', function (): string {
        DB::select('select 1');

        return 'ok';
    });
    config(['log_store.slow_query_ms' => 0]);
    $log = CapturedStory::capture();

    $this->get('/slow-query-test');

    /** @var array<string, mixed> $exceedData */
    $exceedData = $log->line('query.exceed', 'did')['data'];

    /** @var array<string, mixed> $didData */
    $didData = $log->line('http.request', 'did')['data'];

    expect($exceedData['source'])->toMatch('#^app/Support/SlowQueryWatchTest\.php:\d+$#')
        ->and($didData['db'])->toBe(['queries' => 1, 'total_ms' => $exceedData['duration_ms']]);
});

/**
 * `QueryExecuted`'s constructor needs a real connection instance; a
 * `MySqlConnection` over an in-memory SQLite `PDO` stands in for whichever
 * connection actually ran the query.
 *
 * @param  array<int, mixed>  $bindings
 */
function executedQuery(string $sql, array $bindings, float $milliseconds): QueryExecuted
{
    return new QueryExecuted($sql, $bindings, $milliseconds, new MySqlConnection(new PDO('sqlite::memory:')));
}
