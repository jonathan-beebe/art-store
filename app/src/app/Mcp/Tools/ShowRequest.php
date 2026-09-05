<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Logging\Admin\LogFilterInput;
use App\Logging\Admin\LogRowQuery;
use App\Logging\LogStore;
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
 * The story view as a tool: one request's lines in the order they
 * happened, capped where the admin page caps.
 */
#[Description('Show one request\'s story: every log line it wrote, in the order they happened. The request id is the `X-Request-Id` response header, or the `request_id` on any line `search-logs` answered.')]
#[IsReadOnly]
#[IsIdempotent]
final class ShowRequest extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->string()
                ->pattern(trim(LogFilterInput::REQUEST_ID_PATTERN, '/'))
                ->description('The request\'s id (`req_…`).')
                ->required(),
        ];
    }

    public function handle(Request $request, LogStore $store): Response|ResponseFactory
    {
        if ($store->connection === null) {
            return Response::error(SearchLogs::STORE_UNAVAILABLE);
        }

        $input = $request->validate([
            'request_id' => ['required', 'string', 'regex:'.LogFilterInput::REQUEST_ID_PATTERN],
        ]);

        /** @var string $requestId */
        $requestId = $input['request_id'];
        $query = new LogRowQuery($store->connection);
        $total = $query->storyCount($requestId);

        return Response::structured([
            'request_id' => $requestId,
            'total' => $total,
            'capped' => $total > LogRowQuery::STORY_LINE_CAP,
            'lines' => array_map(ToolRows::logRow(...), $query->storyRows($requestId)),
        ]);
    }
}
