<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Analytics\Admin\ChannelTable;
use App\Mcp\RangeInput;
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
 * The `/admin/analytics/channels` table as a tool.
 */
#[Description('Where visitors came from over a range: every channel (direct, search, social, a named campaign, a referring site) with its visitors, listing views, cart adds, orders placed, and orders paid, each against the range before. Ordered by visitors.')]
#[IsReadOnly]
#[IsIdempotent]
final class AnalyticsChannels extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return RangeInput::schema($schema);
    }

    public function handle(Request $request): ResponseFactory
    {
        $input = $request->validate(RangeInput::rules());
        $range = RangeInput::range($input, now()->toDateTimeImmutable());

        return Response::structured([
            'range' => RangeInput::describe($range),
            'channels' => array_map(ToolRows::channelRow(...), ChannelTable::forRange($range)),
        ]);
    }
}
