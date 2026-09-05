<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Logging\Admin\LogFilterInput;
use App\Logging\Admin\LogRowQuery;
use App\Logging\LogStore;
use App\Mcp\LogFilterSchema;
use App\Mcp\ToolInput;
use App\Mcp\ToolRows;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * `/admin/logs` as a tool: the same filters, the same `LogRowQuery`,
 * newest first, paged by `limit`/`offset` with the total match count.
 */
#[Description('Search the log store: every JSON line the app logged, newest first, filtered by site, level, phase, event, ids, message text, an instant range, or any attribute by dotted key. Answers up to `limit` rows and the total match count.')]
#[IsReadOnly]
#[IsIdempotent]
final class SearchLogs extends Tool
{
    public const int DEFAULT_LIMIT = 50;

    public const int MAX_LIMIT = 200;

    public const string STORE_UNAVAILABLE = 'The log store is unavailable in this process; see app/docs/log-store.md.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...LogFilterSchema::fields($schema),
            'include_health' => $schema->boolean()
                ->description('Include the `/up` health-probe requests, hidden by default.'),
            'include_viewer' => $schema->boolean()
                ->description('Include the admin log viewer\'s own `/admin/logs` requests, hidden by default.'),
            'include_mcp' => $schema->boolean()
                ->description('Include this endpoint\'s own `/mcp` requests, hidden by default unless `domain` is `mcp`.'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(self::DEFAULT_LIMIT)
                ->description('Rows to answer, newest first.'),
            'offset' => $schema->integer()->min(0)->default(0)
                ->description('Rows to skip, for paging.'),
        ];
    }

    public function handle(Request $request, LogStore $store): Response|ResponseFactory
    {
        if ($store->connection === null) {
            return Response::error(self::STORE_UNAVAILABLE);
        }

        $request->merge(LogFilterInput::blanked($request->all()));

        $input = $request->validate([
            ...LogFilterInput::rules(),
            'include_health' => ['nullable', 'boolean'],
            'include_viewer' => ['nullable', 'boolean'],
            'include_mcp' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        if (LogFilterInput::valueLacksKey($input)) {
            return Response::error(LogFilterInput::VALUE_NEEDS_KEY);
        }

        $filters = LogFilterInput::filters(
            $input,
            hideHealth: ! ToolInput::boolean($input, 'include_health'),
            hideViewer: ! ToolInput::boolean($input, 'include_viewer'),
            hideMcp: ! ToolInput::boolean($input, 'include_mcp'),
        );
        $limit = ToolInput::integer($input, 'limit', self::DEFAULT_LIMIT);
        $offset = ToolInput::integer($input, 'offset', 0);
        $query = new LogRowQuery($store->connection);

        return Response::structured([
            'total' => $query->count($filters),
            'limit' => $limit,
            'offset' => $offset,
            'rows' => array_map(ToolRows::logRow(...), $query->rows($filters, $limit, $offset)),
        ]);
    }
}
