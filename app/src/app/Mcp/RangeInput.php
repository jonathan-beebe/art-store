<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Domain\Analytics\AnalyticsRange;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;

/**
 * The range every analytics tool takes: `days` (one of the admin site's
 * sizes) ending on `ends_on` (a UTC day, default today), the same
 * `AnalyticsRange` the `/admin/analytics` pages read.
 */
final class RangeInput
{
    public const int DEFAULT_DAYS = 30;

    private const string DAY_PATTERN = '^\d{4}-\d{2}-\d{2}$';

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'days' => ['nullable', 'integer', Rule::in(AnalyticsRange::SIZES)],
            'ends_on' => ['nullable', 'string', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->enum(AnalyticsRange::SIZES)
                ->default(self::DEFAULT_DAYS)
                ->description('The range length in days, ending on `ends_on`. The answer compares it with the same number of days before it.'),
            'ends_on' => $schema->string()
                ->pattern(self::DAY_PATTERN)
                ->description('The last day of the range, `YYYY-MM-DD` in UTC. Defaults to today.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $input  already validated against {@see rules()}
     */
    public static function range(array $input, DateTimeImmutable $now): AnalyticsRange
    {
        $days = ToolInput::integer($input, 'days', self::DEFAULT_DAYS);
        $endsOn = ToolInput::string($input, 'ends_on');

        return AnalyticsRange::of($days, $endsOn === null ? $now : new DateTimeImmutable($endsOn.' 00:00:00', new DateTimeZone('UTC')));
    }

    /**
     * @return array<string, int|string>
     */
    public static function describe(AnalyticsRange $range): array
    {
        $previous = $range->previous();

        return [
            'days' => $range->days,
            'start' => ToolRows::instant($range->start),
            'end' => ToolRows::instant($range->end),
            'previous_start' => ToolRows::instant($previous->start),
            'previous_end' => ToolRows::instant($previous->end),
        ];
    }
}
