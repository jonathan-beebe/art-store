<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Logging\DataRedaction;
use App\Logging\Story;
use App\Logging\StoryEvent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * One `mcp.call` line pair per JSON-RPC message the MCP endpoint receives
 * (docs/spec.md §2.3): `will` names the method — and the tool or resource
 * it addresses, with the tool's arguments — before the key is checked, so
 * every attempt is on the record; `did` carries the HTTP status, the key
 * that opened the call, and the outcome read off the JSON-RPC answer. A
 * call the key guard turned away ends `refused` at `warn`, since a
 * stranger presenting keys is the one thing an operator wants paged on.
 *
 * Runs outside `AuthenticateApiKey`, which hands the key id back through
 * a request attribute once it has one.
 */
final readonly class LogMcpCall
{
    public const string KEY_ATTRIBUTE = 'mcp.key_id';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $call = self::describe($request);
        $subject = trim("{$call['method']} ".($call['tool'] ?? $call['resource'] ?? ''));
        $story = Story::for(StoryEvent::McpCall)->will("mcp {$subject}", array_filter($call, fn (mixed $value): bool => $value !== null));

        try {
            $response = $next($request);
        } catch (Throwable $error) {
            $story->failed($error, "mcp {$subject} broke");

            throw $error;
        }

        $status = $response->getStatusCode();
        $keyId = $request->attributes->get(self::KEY_ATTRIBUTE);
        $data = array_filter([
            'status' => $status,
            'key_id' => is_string($keyId) ? $keyId : null,
        ], fn (mixed $value): bool => $value !== null);

        // The guard's own 401 and 429 bodies are not JSON-RPC answers, so
        // a refusal carries no outcome to read.
        if ($status === 401 || $status === 429) {
            $story->refused("mcp {$subject} {$status}", $data);
        } else {
            $story->did("mcp {$subject} {$status}", [...$data, 'outcome' => self::outcome($response)]);
        }

        return $response;
    }

    /**
     * The JSON-RPC envelope's facts: method, the tool or resource it
     * addresses, and a tool's arguments — a person's own filters, so
     * §2.1's redaction applies to them the way it does to a query string.
     *
     * @return array{method: string, rpc_id: int|string|null, tool: string|null, resource: string|null, arguments: array<array-key, mixed>|null}
     */
    private static function describe(Request $request): array
    {
        $body = $request->json()->all();
        $method = $body['method'] ?? null;
        $rpcId = $body['id'] ?? null;
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : null;

        return [
            'method' => is_string($method) ? $method : 'unknown',
            'rpc_id' => is_int($rpcId) || is_string($rpcId) ? $rpcId : null,
            'tool' => self::stringParam($params, 'name', $method === 'tools/call'),
            'resource' => self::stringParam($params, 'uri', $method === 'resources/read'),
            'arguments' => $arguments === null ? null : DataRedaction::redact($arguments),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $params
     */
    private static function stringParam(array $params, string $key, bool $wanted): ?string
    {
        $value = $params[$key] ?? null;

        return $wanted && is_string($value) ? $value : null;
    }

    /**
     * What the JSON-RPC answer said, when it is readable: a tool that
     * answered `isError`, a protocol error, or a plain result. A streamed
     * answer is not read back.
     */
    private static function outcome(Response $response): string
    {
        if ($response instanceof StreamedResponse) {
            return 'streamed';
        }

        $content = $response->getContent();
        $decoded = is_string($content) && $content !== '' ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            return 'unreadable';
        }

        if (array_key_exists('error', $decoded)) {
            return 'rpc_error';
        }

        $result = $decoded['result'] ?? null;

        return is_array($result) && ($result['isError'] ?? false) === true ? 'tool_error' : 'ok';
    }
}
