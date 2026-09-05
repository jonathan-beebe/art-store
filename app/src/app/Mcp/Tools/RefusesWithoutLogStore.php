<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Logging\LogStore;
use Laravel\Mcp\Response;

/**
 * The guard every log tool opens `handle()` with: refuse before
 * touching `$store->connection`, so a closed store never reaches
 * a query.
 */
trait RefusesWithoutLogStore
{
    public const string STORE_UNAVAILABLE = 'The log store is unavailable in this process; see app/docs/log-store.md.';

    /**
     * Null when the store is open. The error response when it is closed.
     *
     * @phpstan-assert-if-false \PDO $store->connection
     */
    private function refuseWithoutLogStore(LogStore $store): ?Response
    {
        if ($store->connection === null) {
            return Response::error(self::STORE_UNAVAILABLE);
        }

        return null;
    }
}
