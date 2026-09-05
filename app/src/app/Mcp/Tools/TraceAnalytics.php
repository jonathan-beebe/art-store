<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Analytics\AnalyticsReport;
use App\Domain\Identifiers\PrefixedId;
use App\Mcp\ToolInput;
use App\Mcp\ToolRows;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The customer page's "everything this session / this ip did" feeds as
 * a tool, for isolating a scripted or abusive visitor.
 */
#[Description('Follow one browser session (`ses_…`) or one ip across every analytics event it produced in the last `days`, newest first — the trace for isolating a scripted or abusive visitor. Give exactly one of `session_id` or `ip`.')]
#[IsReadOnly]
#[IsIdempotent]
final class TraceAnalytics extends Tool
{
    public const int DEFAULT_DAYS = 30;

    public const int MAX_DAYS = 365;

    public const string ONE_OF = 'give exactly one of session_id or ip';

    private const string SESSION_PREFIX = 'ses';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'session_id' => $schema->string()
                ->description('The browser session\'s id (`ses_…`), from an analytics event or a log line.'),
            'ip' => $schema->string()
                ->description('The client ip, as an analytics event or a log line carries it.'),
            'days' => $schema->integer()->min(1)->max(self::MAX_DAYS)->default(self::DEFAULT_DAYS)
                ->description('How far back to look.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $input = $request->validate([
            'session_id' => ['nullable', 'string', $this->sessionIdRule()],
            'ip' => ['nullable', 'ip'],
            'days' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_DAYS],
        ]);

        $sessionId = ToolInput::string($input, 'session_id');
        $ip = ToolInput::string($input, 'ip');

        if (($sessionId === null) === ($ip === null)) {
            return Response::error(self::ONE_OF);
        }

        $days = ToolInput::integer($input, 'days', self::DEFAULT_DAYS);
        $since = now()->toDateTimeImmutable()->modify("-{$days} days");

        $events = $sessionId !== null
            ? AnalyticsReport::eventsForSession($sessionId, $since)
            : AnalyticsReport::eventsForIp($ip ?? '', $since);

        return Response::structured([
            'session_id' => $sessionId,
            'ip' => $ip,
            'since' => ToolRows::instant($since),
            'events' => array_map(ToolRows::analyticsEvent(...), $events),
        ]);
    }

    private function sessionIdRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || PrefixedId::parse(self::SESSION_PREFIX, $value) === null) {
                $fail('not a session id of the expected shape');
            }
        };
    }
}
