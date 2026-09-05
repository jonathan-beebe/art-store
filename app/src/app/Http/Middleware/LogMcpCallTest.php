<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\ApiKey;
use Illuminate\Testing\TestResponse;
use Tests\CapturedStory;

const CALL_TOKEN = 'artstore_0123456789012345678901234567890123456789';

/**
 * @param  array<string, mixed>  $params
 * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
 */
function call(?string $token, string $method, array $params = []): TestResponse
{
    $headers = ['Accept' => 'application/json, text/event-stream'];

    if ($token !== null) {
        $headers['Authorization'] = "Bearer {$token}";
    }

    return test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => $method, 'params' => $params], $headers);
}

it('writes a will and a did line for a tool call, naming the tool, its arguments, the key, and the outcome', function (): void {
    $admin = Admin::factory()->create();
    $key = ApiKey::factory()->for($admin)->forToken(CALL_TOKEN)->create();
    $log = CapturedStory::capture();

    call(CALL_TOKEN, 'tools/call', ['name' => 'describe', 'arguments' => ['q' => 'shopper@example.com']])->assertOk();

    $will = $log->line('mcp.call', 'will');
    $did = $log->line('mcp.call', 'did');

    expect($will['msg'])->toBe('mcp tools/call describe')
        ->and($will['data'])->toBe(['method' => 'tools/call', 'rpc_id' => 3, 'tool' => 'describe', 'arguments' => ['q' => '[redacted]']])
        ->and($will['txn_id'])->toStartWith('txn_')
        ->and($did['msg'])->toBe('mcp tools/call describe 200')
        ->and($did['data'])->toBe(['status' => 200, 'key_id' => $key->id, 'outcome' => 'ok'])
        ->and($did['actor_type'])->toBe('admin')
        ->and($did['actor_id'])->toBe($admin->id)
        ->and($did['duration_ms'])->toBeInt();
});

it('reads a tool error and a protocol error off the answer', function (): void {
    ApiKey::factory()->forToken(CALL_TOKEN)->create();
    $log = CapturedStory::capture();

    call(CALL_TOKEN, 'tools/call', ['name' => 'search-logs', 'arguments' => ['event' => 'nope']])->assertOk();
    call(CALL_TOKEN, 'no/such/method')->assertOk();

    $outcomes = array_map(function (array $line): mixed {
        $data = $line['data'] ?? null;

        return is_array($data) ? ($data['outcome'] ?? null) : null;
    }, $log->linesFor('mcp.call'));

    expect(array_values(array_filter($outcomes)))->toBe(['tool_error', 'rpc_error']);
});

it('names a resource read', function (): void {
    ApiKey::factory()->forToken(CALL_TOKEN)->create();
    $log = CapturedStory::capture();

    call(CALL_TOKEN, 'resources/read', ['uri' => 'artstore://guide'])->assertOk();

    /** @var array<string, mixed> $data */
    $data = $log->line('mcp.call', 'will')['data'];

    expect($log->line('mcp.call', 'will')['msg'])->toBe('mcp resources/read artstore://guide')
        ->and($data['resource'])->toBe('artstore://guide');
});

it('refuses at warn when no key, a bad key, or a revoked key opens the call, with no key id', function (): void {
    ApiKey::factory()->forToken(CALL_TOKEN)->revoked()->create();
    $log = CapturedStory::capture();

    call(null, 'ping')->assertStatus(401);
    call('artstore_9999999999999999999999999999999999999999', 'ping')->assertStatus(401);
    call(CALL_TOKEN, 'ping')->assertStatus(401);

    $refusals = array_values(array_filter($log->linesFor('mcp.call'), fn (array $line): bool => $line['phase'] === 'refused'));

    expect($refusals)->toHaveCount(3)
        ->and($refusals[0]['level'])->toBe('warn')
        ->and($refusals[0]['msg'])->toBe('⚠️ mcp ping 401')
        ->and($refusals[0]['data'])->toBe(['status' => 401])
        ->and($log->linesFor('mcp.call'))->toHaveCount(6);
});

it('reads an unparsable body as an unknown method', function (): void {
    $log = CapturedStory::capture();

    $this->call('POST', '/mcp', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'], 'not json');

    expect($log->line('mcp.call', 'will')['msg'])->toBe('mcp unknown')
        ->and($log->line('mcp.call', 'will')['data'])->toBe(['method' => 'unknown']);
});
