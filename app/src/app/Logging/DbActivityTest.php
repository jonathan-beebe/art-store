<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\MySqlConnection;
use PDO;

it('reports zero queries and zero time before anything runs', function (): void {
    DbActivity::reset();

    expect(DbActivity::snapshot())->toBe(['queries' => 0, 'total_ms' => 0.0]);
});

it('tallies a query into the count and the summed time', function (): void {
    DbActivity::reset();

    DbActivity::listen(query(4.2));
    DbActivity::listen(query(1.3));

    expect(DbActivity::snapshot())->toBe(['queries' => 2, 'total_ms' => 5.5]);
});

it('rounds the summed time to two decimal places', function (): void {
    DbActivity::reset();

    DbActivity::listen(query(1.111));
    DbActivity::listen(query(1.111));

    expect(DbActivity::snapshot()['total_ms'])->toBe(2.22);
});

it('starts the next tally at zero', function (): void {
    DbActivity::reset();
    DbActivity::listen(query(9.0));

    DbActivity::reset();

    expect(DbActivity::snapshot())->toBe(['queries' => 0, 'total_ms' => 0.0]);
});

/**
 * `QueryExecuted`'s constructor needs a real connection instance; a
 * `MySqlConnection` over an in-memory SQLite `PDO` stands in for whichever
 * connection actually ran the query.
 */
function query(float $milliseconds): QueryExecuted
{
    return new QueryExecuted('select 1', [], $milliseconds, new MySqlConnection(new PDO('sqlite::memory:')));
}
