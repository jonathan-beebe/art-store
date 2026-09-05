<?php

declare(strict_types=1);

namespace App\Mcp;

it('reads an integer, a numeric string, or the default', function (): void {
    expect(ToolInput::integer(['n' => 7], 'n', 1))->toBe(7)
        ->and(ToolInput::integer(['n' => '12'], 'n', 1))->toBe(12)
        ->and(ToolInput::integer(['n' => 'seven'], 'n', 1))->toBe(1)
        ->and(ToolInput::integer([], 'n', 1))->toBe(1);
});

it('reads a string, treating blank and absent as null', function (): void {
    expect(ToolInput::string(['q' => 'favorite'], 'q'))->toBe('favorite')
        ->and(ToolInput::string(['q' => ''], 'q'))->toBeNull()
        ->and(ToolInput::string(['q' => 3], 'q'))->toBeNull()
        ->and(ToolInput::string([], 'q'))->toBeNull();
});

it('reads a boolean the way JSON or a form sends it', function (): void {
    expect(ToolInput::boolean(['x' => true], 'x'))->toBeTrue()
        ->and(ToolInput::boolean(['x' => '1'], 'x'))->toBeTrue()
        ->and(ToolInput::boolean(['x' => 'false'], 'x'))->toBeFalse()
        ->and(ToolInput::boolean([], 'x'))->toBeFalse();
});
