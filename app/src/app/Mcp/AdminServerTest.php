<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Models\ApiKey;
use Illuminate\Testing\TestResponse;

const SERVER_TOKEN = 'artstore_0123456789012345678901234567890123456789';

/**
 * One JSON-RPC call over the real route, with the key: what a client
 * such as Claude Code sends after `claude mcp add`.
 *
 * @param  array<string, mixed>  $params
 * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
 */
function rpc(string $method, array $params = []): TestResponse
{
    return test()->postJson(
        '/mcp',
        ['jsonrpc' => '2.0', 'id' => 7, 'method' => $method, 'params' => $params],
        ['Accept' => 'application/json, text/event-stream', 'Authorization' => 'Bearer '.SERVER_TOKEN],
    );
}

beforeEach(function (): void {
    ApiKey::factory()->forToken(SERVER_TOKEN)->create();
});

it('introduces itself by name with instructions that point at describe', function (): void {
    rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Art Store')
        ->assertJsonPath('result.instructions', AdminServer::INSTRUCTIONS);
});

it('lists every tool the server declares, read-only, in one page', function (): void {
    $response = rpc('tools/list')->assertOk();

    /** @var list<array{name: string, annotations: array<string, bool>}> $tools */
    $tools = $response->json('result.tools');

    expect(array_column($tools, 'name'))->toBe([
        'describe', 'search-logs', 'show-request', 'tally-logs',
        'analytics-events', 'analytics-channels', 'analytics-actors', 'trace-analytics',
    ])
        ->and(count(AdminServer::TOOLS))->toBe(count($tools))
        ->and(collect($tools)->every(fn (array $tool): bool => ($tool['annotations']['readOnlyHint'] ?? false) === true))->toBeTrue()
        ->and($response->json('result.nextCursor'))->toBeNull();
});

it('offers the guide as a markdown resource', function (): void {
    rpc('resources/list')
        ->assertOk()
        ->assertJsonPath('result.resources.0.uri', 'artstore://guide')
        ->assertJsonPath('result.resources.0.mimeType', 'text/markdown');

    $read = rpc('resources/read', ['uri' => 'artstore://guide'])->assertOk();

    expect($read->json('result.contents.0.text'))->toContain('## Tools', '`search-logs`');
});

it('runs a tool through the route as the key\'s admin', function (): void {
    $response = rpc('tools/call', ['name' => 'describe', 'arguments' => []])->assertOk();

    expect($response->json('result.content.0.text'))->toStartWith('# Art Store MCP')
        ->and($response->json('result.isError'))->toBeFalse();
});
