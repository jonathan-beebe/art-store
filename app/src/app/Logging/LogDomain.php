<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * The three sites docs/spec.md names, and the MCP endpoint beside them.
 * A stored line carries no site field of its own — the admin log viewer's
 * `?domain=` filter derives one from the line's request, correlating
 * against that request's opening `http.request` line and prefix-matching
 * its `data.path` (`App\Logging\Admin\LogRowQuery`).
 */
enum LogDomain: string
{
    case Shop = 'shop';
    case Seller = 'seller';
    case Admin = 'admin';
    case Mcp = 'mcp';
}
