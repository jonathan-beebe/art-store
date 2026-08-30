<?php

declare(strict_types=1);

namespace Tests;

use App\Logging\Admin\LogRowQuery;
use App\Logging\LogLine;
use App\Logging\LogStore;
use JsonException;
use PDO;
use RuntimeException;

/**
 * Shared fixtures for the admin log viewer's tests
 * (`App\Logging\Admin\LogRowQueryTest`,
 * `App\Http\Controllers\Admin\LogControllerTest`,
 * `App\Http\Requests\Admin\LogsQueryRequestTest`): a real `LogStore` against
 * a temp file, written to through its own `append()`/`flush()` — the viewer
 * only ever reads what the store actually accepted, so its tests write the
 * same way.
 */
final class LogViewerFixtures
{
    /**
     * @param  list<array<string, mixed>>  $lines  each a §2.1-shaped payload
     */
    public static function store(array $lines): LogStore
    {
        $store = LogStore::open(LogStoreFixtures::tempFile());

        foreach ($lines as $fields) {
            $store->append(self::parse($fields));
        }

        $store->flush();

        return $store;
    }

    /**
     * A ready query over a store built and written the same way `store()`
     * does — the shorthand `App\Logging\Admin\LogRowQueryTest` reaches for
     * when it has no need of the `LogStore` itself.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public static function query(array $lines): LogRowQuery
    {
        return new LogRowQuery(self::connection(self::store($lines)));
    }

    public static function connection(LogStore $store): PDO
    {
        return $store->connection ?? throw new RuntimeException('expected the log store to be enabled');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function line(array $overrides = []): array
    {
        return array_replace([
            'ts' => '2026-08-24T12:00:00.000Z',
            'level' => 'info',
            'event' => 'order.place',
            'phase' => 'did',
            'msg' => 'placed the order',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private static function parse(array $fields): LogLine
    {
        try {
            $json = json_encode($fields, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $json = '{}';
        }

        return LogLine::parse($json);
    }
}
