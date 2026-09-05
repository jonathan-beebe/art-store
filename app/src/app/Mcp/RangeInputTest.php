<?php

declare(strict_types=1);

namespace App\Mcp;

use Illuminate\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;

it('defaults to thirty days ending today', function (): void {
    $range = RangeInput::range([], $this->moment('2026-09-05 15:30:00'));

    expect(RangeInput::describe($range))->toBe([
        'days' => 30,
        'start' => '2026-08-07T00:00:00Z',
        'end' => '2026-09-05T23:59:59Z',
        'previous_start' => '2026-07-08T00:00:00Z',
        'previous_end' => '2026-08-06T23:59:59Z',
    ]);
});

it('reads days and the last day from the input', function (): void {
    $range = RangeInput::range(['days' => 7, 'ends_on' => '2026-08-24'], $this->moment('2026-09-05 15:30:00'));

    expect($range->days)->toBe(7)
        ->and(RangeInput::describe($range)['start'])->toBe('2026-08-18T00:00:00Z')
        ->and(RangeInput::describe($range)['end'])->toBe('2026-08-24T23:59:59Z');
});

it('accepts only the admin site\'s range sizes and a whole day', function (): void {
    expect(Validator::make(['days' => 7, 'ends_on' => '2026-08-24'], RangeInput::rules())->passes())->toBeTrue()
        ->and(Validator::make(['days' => 8], RangeInput::rules())->passes())->toBeFalse()
        ->and(Validator::make(['ends_on' => '2026-08-24T10:00:00Z'], RangeInput::rules())->passes())->toBeFalse();
});

it('describes both fields to a client', function (): void {
    /** @var array{properties: array<string, array<string, mixed>>} $schema */
    $schema = JsonSchema::object(RangeInput::schema(...))->toArray();

    expect($schema['properties']['days']['enum'])->toBe([7, 30, 90])
        ->and($schema['properties']['days']['default'])->toBe(30)
        ->and($schema['properties']['ends_on']['pattern'])->toBe('^\d{4}-\d{2}-\d{2}$');
});
