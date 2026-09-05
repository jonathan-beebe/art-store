<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Analytics\Admin\ActorList;
use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\ActorSort;
use App\Mcp\RangeInput;
use App\Mcp\ToolInput;
use App\Mcp\ToolRows;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The `/admin/analytics/actors` list as a tool.
 */
#[Description('The customers active over a range — anonymous shoppers included — each with their event count, their busiest hour\'s events, distinct subjects, the ips they came from, first and last seen, and whether their peak looks scripted (`flagged`). Sorted by activity or recency, paged.')]
#[IsReadOnly]
#[IsIdempotent]
final class AnalyticsActors extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...RangeInput::schema($schema),
            'sort' => $schema->string()->enum(array_column(ActorSort::cases(), 'value'))->default(ActorSort::Active->value)
                ->description('`active`: most events first. `recent`: last seen first.'),
            'kind' => $schema->string()->enum(array_column(ActorKindFilter::cases(), 'value'))->default(ActorKindFilter::All->value)
                ->description('Which customers: `all`, `anonymous` (no verified email), or `verified`.'),
            'q' => $schema->string()->max(100)
                ->description('Narrow to actors whose id, email, or ip contains this text.'),
            'page' => $schema->integer()->min(1)->default(1)
                ->description('The page of 25 to answer.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $input = $request->validate([
            ...RangeInput::rules(),
            'sort' => ['nullable', Rule::enum(ActorSort::class)],
            'kind' => ['nullable', Rule::enum(ActorKindFilter::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $range = RangeInput::range($input, now()->toDateTimeImmutable());
        $sort = ActorSort::tryFrom(ToolInput::string($input, 'sort') ?? '') ?? ActorSort::Active;
        $kind = ActorKindFilter::tryFrom(ToolInput::string($input, 'kind') ?? '') ?? ActorKindFilter::All;
        $search = ToolInput::string($input, 'q');
        $page = ToolInput::integer($input, 'page', 1);

        $actors = ActorList::forRange($range, $sort, $kind, $search, $page);

        return Response::structured([
            'range' => RangeInput::describe($range),
            'page' => [
                'number' => $actors->page->number,
                'size' => $actors->page->size,
                'total_count' => $actors->page->totalCount,
                'is_last' => $actors->page->isLast,
            ],
            'actors' => array_map(ToolRows::actorSummary(...), $actors->rows),
        ]);
    }
}
