<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\AdminServer;

it('is the first tool and answers the guide', function (): void {
    AdminServer::tool(Describe::class)
        ->assertOk()
        ->assertName('describe')
        ->assertSee('# Art Store MCP')
        ->assertSee('`search-logs`');
});
