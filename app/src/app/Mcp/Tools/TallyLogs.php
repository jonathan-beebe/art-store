<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Logging\Admin\LogFilterInput;
use App\Logging\Admin\LogRowQuery;
use App\Logging\LogStore;
use App\Mcp\LogFilterSchema;
use App\Mcp\ToolInput;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The viewer's level tiles as a tool: how many lines per level match
 * the filters, with `level` itself ignored, so one call answers "any
 * errors in the last hour" without paging rows.
 */
#[Description('Count the log lines matching the filters, per level (`error`, `warn`, `info`, `debug`). The cheap first question: any errors or warnings in a window, for a site, for an actor, for an event. Ignores `level` itself.')]
#[IsReadOnly]
#[IsIdempotent]
final class TallyLogs extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = LogFilterSchema::fields($schema);
        unset($fields['level']);

        return [
            ...$fields,
            'include_health' => $schema->boolean()
                ->description('Include the `/up` health-probe requests, hidden by default.'),
            'include_viewer' => $schema->boolean()
                ->description('Include the admin log viewer\'s own `/admin/logs` requests, hidden by default.'),
            'include_mcp' => $schema->boolean()
                ->description('Include this endpoint\'s own `/mcp` requests, hidden by default unless `domain` is `mcp`.'),
        ];
    }

    public function handle(Request $request, LogStore $store): Response|ResponseFactory
    {
        if ($store->connection === null) {
            return Response::error(SearchLogs::STORE_UNAVAILABLE);
        }

        $request->merge(LogFilterInput::blanked($request->all()));

        $input = $request->validate([
            ...LogFilterInput::rules(),
            'include_health' => ['nullable', 'boolean'],
            'include_viewer' => ['nullable', 'boolean'],
            'include_mcp' => ['nullable', 'boolean'],
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
        $tallies = (new LogRowQuery($store->connection))->levelTallies($filters);

        return Response::structured([
            'total' => array_sum($tallies),
            'levels' => $tallies,
        ]);
    }
}
