<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\ActorSort;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use App\Logging\LogDomain;
use App\Logging\StoryEvent;
use App\Logging\StoryLevel;
use App\Logging\StoryPhase;
use BackedEnum;
use Laravel\Mcp\Server\Tool;

/**
 * The server's self-description (app/docs/mcp.md § "Self-discovery"):
 * every tool by name, and the vocabulary the tools accept, read from the
 * same enums that validate it so the guide can never drift from the
 * filters. The `describe` tool and the `artstore://guide` resource both
 * answer with this text.
 */
final class Guide
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  list<class-string<Tool>>  $tools
     */
    public static function markdown(array $tools, int $logRetentionDays, int $analyticsRetentionDays): string
    {
        $lines = [
            '# Art Store MCP',
            '',
            'Read-only tools over the store\'s log store and analytics store, for the admin whose key opened this session.',
            'Every id is a prefixed ULID: `adm_…` admin, `sel_…` seller, `cus_…` customer (anonymous shoppers included), `ord_…` order, `lst_…` listing, `req_…` request, `ses_…` browser session, `txn_…` unit of work.',
            'Every instant is UTC, written `YYYY-MM-DDTHH:MM:SSZ`.',
            '',
            '## Tools',
            '',
        ];

        foreach ($tools as $class) {
            $tool = new $class;
            $lines[] = "- `{$tool->name()}` — {$tool->description()}";
        }

        $lines = [
            ...$lines,
            '',
            '## Log lines',
            '',
            "The log store mirrors every JSON line the app logs, and keeps {$logRetentionDays} days of them. Each line carries `ts`, `level`, `event`, `phase`, `msg`, and when known `request_id`, `session_id`, `actor_type`, `actor_id`, `txn_id`, `duration_ms`, `data` (an object of the small facts the line is about), and `error` (`{type, message}` on a `failed` line).",
            '',
            '- Levels: '.self::backtick(StoryLevel::cases()),
            '- Phases: '.self::backtick(StoryPhase::cases()).' — `will` opens a step, `did` closes it, `refused` is a rule the app declined on, `failed` is an error.',
            '- Domains: '.self::backtick(LogDomain::cases()).' — the site a line\'s request hit, derived from its path; `mcp` is this endpoint\'s own traffic.',
            '- Events: '.self::backtick(StoryEvent::cases()),
            '- `http.request` is the opening line of every request; its `data` carries `method`, `path`, and on the closing `did` line `status` and `duration_ms`.',
            '- `rate_limit.exceed` and `query.exceed` are the two `warn` lines an operator watches for; `failed` lines at any event are errors.',
            '- Filter by any attribute with `key` (a dotted path such as `data.order_id` or `error.type`) and `value`. `from`/`to` bound `ts`. `msg` matches anywhere in the message.',
            '- Health probes (`/up`) and the admin log viewer\'s own requests are hidden unless `include_health` / `include_viewer` is set.',
            '',
            '## Analytics',
            '',
            "The analytics store counts page views and listing interactions, and keeps {$analyticsRetentionDays} days of events.",
            '',
            '- Ranges are '.implode(', ', array_map(fn (int $days): string => "`{$days}`", AnalyticsRange::SIZES)).' days ending on `ends_on` (default today), each compared with the same number of days before it.',
            '- Event names: '.self::backtick(AnalyticsEventName::cases()).' — plus `page.view`, the roll-up of counted HTML page loads.',
            '- Actors are customers: '.self::backtick(ActorKindFilter::cases()).' kinds, sorted by '.self::backtick(ActorSort::cases()).'.',
            '- `trace-analytics` follows one browser session (`ses_…`) or one ip across everything it did, for isolating a scripted or abusive visitor.',
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  list<BackedEnum>  $cases
     */
    private static function backtick(array $cases): string
    {
        return implode(', ', array_map(fn (BackedEnum $case): string => "`{$case->value}`", $cases));
    }
}
