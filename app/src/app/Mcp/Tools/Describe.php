<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\AdminServer;
use App\Mcp\Guide;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Describe this server: every tool, and the exact vocabulary the filters accept — log event names, levels, phases, domains, analytics event names, id shapes, and retention windows. Call this first.')]
#[IsReadOnly]
#[IsIdempotent]
final class Describe extends Tool
{
    public function handle(): Response
    {
        return Response::text(Guide::markdown(
            AdminServer::TOOLS,
            (int) config('log_store.retention_days'),
            (int) config('analytics.retention_days'),
        ));
    }
}
