<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Admin;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;

const MCP_TOKEN = 'artstore_0123456789012345678901234567890123456789';

/**
 * The smallest JSON-RPC message the endpoint answers: `ping`. A 200 here
 * means the middleware let the request through to the server.
 *
 * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
 */
function ping(?string $token): TestResponse
{
    $headers = ['Accept' => 'application/json, text/event-stream'];

    if ($token !== null) {
        $headers['Authorization'] = "Bearer {$token}";
    }

    return test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], $headers);
}

it('answers 401 as JSON, with a bearer challenge, when no key is presented', function (): void {
    ping(null)
        ->assertStatus(401)
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"')
        ->assertJson(['error' => 'unauthenticated']);
});

it('answers 401 for a malformed, unknown, or revoked key', function (string $token, bool $revoked): void {
    $factory = ApiKey::factory()->forToken(MCP_TOKEN);
    ($revoked ? $factory->revoked() : $factory)->create();

    ping($token)->assertStatus(401);
})->with([
    'malformed' => ['not-a-key', false],
    'unknown' => ['artstore_9999999999999999999999999999999999999999', false],
    'revoked' => [MCP_TOKEN, true],
]);

it('lets an active key through as its admin and records the use', function (): void {
    $admin = Admin::factory()->create();
    $key = ApiKey::factory()->for($admin)->forToken(MCP_TOKEN)->create();

    $response = ping(MCP_TOKEN);

    $response->assertOk()->assertJsonPath('id', 1);
    expect($key->refresh()->last_used_at)->not->toBeNull();
});

it('spends the mcp_request limit per key and answers 429 with Retry-After once it is gone', function (): void {
    Config::set('rate_limits.mcp_request', RateLimitValue::parse('2/1h', 'RATE_LIMIT_MCP_REQUEST'));
    ApiKey::factory()->forToken(MCP_TOKEN)->create();

    ping(MCP_TOKEN)->assertOk();
    ping(MCP_TOKEN)->assertOk();
    ping(MCP_TOKEN)
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('error', 'rate limited');
});
