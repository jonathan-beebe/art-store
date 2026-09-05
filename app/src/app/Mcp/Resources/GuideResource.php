<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Mcp\AdminServer;
use App\Mcp\Guide;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * The guide as a resource, for a client that reads resources before it
 * calls tools; the `describe` tool answers with the same text.
 */
#[Name('guide')]
#[Uri('artstore://guide')]
#[MimeType('text/markdown')]
#[Description('What this server offers: every tool, and the vocabulary its filters accept.')]
final class GuideResource extends Resource
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
