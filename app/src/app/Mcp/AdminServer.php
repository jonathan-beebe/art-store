<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Mcp\Resources\GuideResource;
use App\Mcp\Tools\AnalyticsActors;
use App\Mcp\Tools\AnalyticsChannels;
use App\Mcp\Tools\AnalyticsEvents;
use App\Mcp\Tools\Describe;
use App\Mcp\Tools\SearchLogs;
use App\Mcp\Tools\ShowRequest;
use App\Mcp\Tools\TallyLogs;
use App\Mcp\Tools\TraceAnalytics;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The MCP server the admin site hosts at `POST /mcp` (docs/spec.md §5
 * "MCP endpoint", app/docs/mcp.md): read-only tools over the log store
 * and the analytics store, behind an admin's api key. The tools call the
 * same readers the admin pages call; nothing here writes.
 */
#[Name('Art Store')]
#[Version('1.0.0')]
#[Instructions(self::INSTRUCTIONS)]
final class AdminServer extends Server
{
    public const string INSTRUCTIONS = <<<'MARKDOWN'
        Read-only access to the Art Store's log store and analytics store, as the admin whose key opened this session.
        Call `describe` first (or read `artstore://guide`): it lists every tool and the exact vocabulary the filters accept — log event names, levels, phases, domains, analytics event names, id shapes, and retention windows.
        Every id is a prefixed ULID (`adm_…`, `sel_…`, `cus_…`, `ord_…`); every instant is UTC.
        MARKDOWN;

    /** @var list<class-string<Server\Tool>> */
    public const array TOOLS = [
        Describe::class,
        SearchLogs::class,
        ShowRequest::class,
        TallyLogs::class,
        AnalyticsEvents::class,
        AnalyticsChannels::class,
        AnalyticsActors::class,
        TraceAnalytics::class,
    ];

    /** @var array<int, class-string<Server\Tool>> */
    protected array $tools = self::TOOLS;

    /** @var array<int, class-string<Server\Resource>> */
    protected array $resources = [
        GuideResource::class,
    ];
}
