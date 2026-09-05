<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\LogMcpCall;
use App\Mcp\AdminServer;
use Laravel\Mcp\Facades\Mcp;

// The MCP endpoint (docs/spec.md §5 "MCP endpoint", app/docs/mcp.md): one
// POST path, one server, one key guard. The package registers the POST
// itself and answers GET and DELETE on the same path with 405; the
// package's provider loads this file, so `bootstrap/app.php` does not
// name it. The call log wraps the key guard, so a refused key is a
// `refused` line and not a request that vanished.
Mcp::web('/mcp', AdminServer::class)
    ->middleware([LogMcpCall::class, AuthenticateApiKey::class])
    ->name('mcp');
