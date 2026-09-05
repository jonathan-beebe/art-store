<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Logging\Admin\LogFilterInput;
use App\Logging\StoryEvent;
use Illuminate\JsonSchema\JsonSchema;

it('describes exactly the fields the filter rules validate, with every enum spelled out', function (): void {
    /** @var array{properties: array<string, array<string, mixed>>} $schema */
    $schema = JsonSchema::object(LogFilterSchema::fields(...))->toArray();

    expect(array_keys($schema['properties']))->toBe(LogFilterInput::FIELDS)
        ->and($schema['properties']['event']['enum'])->toBe(array_column(StoryEvent::cases(), 'value'))
        ->and($schema['properties']['domain']['enum'])->toBe(['shop', 'seller', 'admin', 'mcp'])
        ->and($schema['properties']['request']['pattern'])->toBe('^[A-Za-z0-9_-]{1,64}$');
});
