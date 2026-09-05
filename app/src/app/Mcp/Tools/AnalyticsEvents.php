<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Analytics\Admin\EventTotals;
use App\Mcp\RangeInput;
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
 * The `/admin/analytics` event table as a tool.
 */
#[Description('Every analytics event name over a range — listing views, favorites, cart adds, checkouts, orders, store views, help outcomes, and the page-view roll-up — each with its count, the count for the range before, the change, one count per day, and distinct subjects and actors.')]
#[IsReadOnly]
#[IsIdempotent]
final class AnalyticsEvents extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...RangeInput::schema($schema),
            'q' => $schema->string()->max(100)
                ->description('Narrow to event names or labels containing this text, case-insensitive.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $input = $request->validate([
            ...RangeInput::rules(),
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $range = RangeInput::range($input, now()->toDateTimeImmutable());
        $search = ToolInput::string($input, 'q');

        return Response::structured([
            'range' => RangeInput::describe($range),
            'events' => array_map(ToolRows::eventTotal(...), EventTotals::forRange($range, $search)),
        ]);
    }
}
