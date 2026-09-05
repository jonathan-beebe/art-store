<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Mcp\AdminServer;
use App\Mcp\Guide;
use Illuminate\Support\Facades\Config;

it('answers the same guide the describe tool does, with the configured retention windows', function (): void {
    Config::set('log_store.retention_days', 9);
    Config::set('analytics.retention_days', 45);

    AdminServer::resource(GuideResource::class)
        ->assertOk()
        ->assertSee(Guide::markdown(AdminServer::TOOLS, 9, 45));
});
