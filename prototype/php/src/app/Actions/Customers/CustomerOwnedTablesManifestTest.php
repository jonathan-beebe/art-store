<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\Customers\CustomerOwnedTables;
use Illuminate\Support\Facades\Schema;

/**
 * A schema-level check on `CustomerOwnedTables`, kept beside the action that
 * reads it rather than in `App\Domain`, which has no database to introspect.
 * A table added later with its own `customer_id` column has to land in
 * `CustomerOwnedTables::all()` or `leftBehind()` before this passes, so a
 * merge cannot silently miss it.
 */
it('accounts for every customer_id column in the schema', function (): void {
    $accountedFor = [...array_keys(CustomerOwnedTables::all()), ...array_keys(CustomerOwnedTables::leftBehind())];

    /** @var list<array{name: string}> $tables */
    $tables = Schema::getTables();

    $tablesWithCustomerId = [];
    foreach ($tables as $table) {
        /** @var list<array{name: string}> $columns */
        $columns = Schema::getColumns($table['name']);

        if (in_array('customer_id', array_column($columns, 'name'), true)) {
            $tablesWithCustomerId[] = $table['name'];
        }
    }

    // A schema with nothing named `customer_id` would make the diff below
    // vacuous, so the test pins the shape it expects to see too.
    expect($tablesWithCustomerId)->not->toBeEmpty()
        ->and(array_values(array_diff($tablesWithCustomerId, $accountedFor)))->toBe([]);
});
