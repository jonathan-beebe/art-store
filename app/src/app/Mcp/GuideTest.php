<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Domain\Analytics\AnalyticsEventName;
use App\Logging\StoryEvent;

it('names every tool with its description', function (): void {
    $guide = Guide::markdown(AdminServer::TOOLS, 14, 30);

    foreach (AdminServer::TOOLS as $class) {
        $tool = new $class;
        expect($guide)->toContain("- `{$tool->name()}` — {$tool->description()}");
    }
});

it('spells out every log event and analytics event name, and both retention windows', function (): void {
    $guide = Guide::markdown(AdminServer::TOOLS, 14, 30);

    foreach (StoryEvent::cases() as $event) {
        expect($guide)->toContain("`{$event->value}`");
    }

    foreach (AnalyticsEventName::cases() as $event) {
        expect($guide)->toContain("`{$event->value}`");
    }

    expect($guide)->toContain('keeps 14 days of them', 'keeps 30 days of events', '`mcp`', '`7`, `30`, `90`');
});
