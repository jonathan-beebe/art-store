<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Logging\Admin\LogFilterInput;
use App\Logging\LogDomain;
use App\Logging\StoryEvent;
use App\Logging\StoryLevel;
use App\Logging\StoryPhase;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;

/**
 * The JSON schema of the log filters `LogFilterInput` validates, one
 * property per field, with every enum's cases spelled out so an agent
 * sees the vocabulary in the tool listing without a second call.
 */
final class LogFilterSchema
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, mixed>
     */
    public static function fields(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->enum(self::values(LogDomain::cases()))
                ->description('The site the line\'s request hit: `shop`, `seller`, `admin`, or `mcp` (this endpoint).'),
            'level' => $schema->string()->enum(self::values(StoryLevel::cases()))
                ->description('The line\'s level.'),
            'phase' => $schema->string()->enum(self::values(StoryPhase::cases()))
                ->description('The line\'s phase; `failed` lines are errors, `refused` lines are rules the app declined on.'),
            'event' => $schema->string()->enum(self::values(StoryEvent::cases()))
                ->description('The dotted event name.'),
            'request' => $schema->string()->pattern(trim(LogFilterInput::REQUEST_ID_PATTERN, '/'))
                ->description('One request\'s id (`req_…`), as the `X-Request-Id` response header carries it.'),
            'txn' => $schema->string()
                ->description('One unit of work\'s id (`txn_…`).'),
            'session' => $schema->string()
                ->description('One browser session\'s id (`ses_…`).'),
            'actor' => $schema->string()
                ->description('The actor\'s id: `adm_…`, `sel_…`, or `cus_…`.'),
            'msg' => $schema->string()
                ->description('Text the message contains, case-insensitive.'),
            'from' => $schema->string()->pattern(trim(LogFilterInput::ISO_INSTANT_PATTERN, '/'))
                ->description('Only lines at or after this UTC instant, `YYYY-MM-DDTHH:MM:SSZ`.'),
            'to' => $schema->string()->pattern(trim(LogFilterInput::ISO_INSTANT_PATTERN, '/'))
                ->description('Only lines at or before this UTC instant, `YYYY-MM-DDTHH:MM:SSZ`.'),
            'key' => $schema->string()
                ->description('A dotted path into the stored line, such as `data.order_id` or `error.type`; with `value`, only lines where it equals the value, alone, only lines that carry it.'),
            'value' => $schema->string()
                ->description('The value `key` must equal. Needs `key`.'),
        ];
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return list<string>
     */
    private static function values(array $cases): array
    {
        return array_map(fn (BackedEnum $case): string => (string) $case->value, $cases);
    }
}
