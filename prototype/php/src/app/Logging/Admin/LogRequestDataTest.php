<?php

declare(strict_types=1);

namespace App\Logging\Admin;

it('decodes null as no data', function (): void {
    expect(LogRequestData::decode(null))->toBe([]);
});

it('decodes text that is not a JSON object as no data', function (): void {
    expect(LogRequestData::decode('not json'))->toBe([])
        ->and(LogRequestData::decode('"a json string"'))->toBe([])
        ->and(LogRequestData::decode('42'))->toBe([]);
});

it('decodes a JSON object', function (): void {
    expect(LogRequestData::decode('{"method":"GET","status":200}'))->toBe(['method' => 'GET', 'status' => 200]);
});

it('reads a string field, null when absent or the wrong type', function (): void {
    expect(LogRequestData::stringField(['method' => 'GET'], 'method'))->toBe('GET')
        ->and(LogRequestData::stringField(['method' => 'GET'], 'path'))->toBeNull()
        ->and(LogRequestData::stringField(['method' => 1], 'method'))->toBeNull();
});

it('reads an int field, null when absent or the wrong type', function (): void {
    expect(LogRequestData::intField(['status' => 200], 'status'))->toBe(200)
        ->and(LogRequestData::intField(['status' => 200], 'other'))->toBeNull()
        ->and(LogRequestData::intField(['status' => '200'], 'status'))->toBeNull();
});
