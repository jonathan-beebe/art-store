<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\ApiKeyToken;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Models\ApiKey;
use App\Support\RateLimiting\RateLimitGate;
use App\Support\Story;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The MCP endpoint's guard (docs/spec.md §5 "MCP endpoint"): a bearer
 * token that hashes to an active `api_keys` row signs the request in as
 * that row's admin, on the `admin` guard, so a tool's `$request->user()`
 * is the admin and the request's log lines carry them as the actor. A
 * missing, malformed, unknown, or revoked token answers 401 as JSON —
 * this route sits outside the `web` group, so the framework's redirect
 * to a login page never applies; the package's own global middleware
 * stamps the `WWW-Authenticate` challenge on every 401 — and the
 * `mcp_request` limit (docs/spec.md §3) is spent per key before the
 * server runs. The key's id is left on the request for `LogMcpCall`,
 * which wraps this guard and closes the call's `mcp.call` line.
 */
final readonly class AuthenticateApiKey
{
    public function __construct(private RateLimitGate $rateLimit) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || ! ApiKeyToken::isWellFormed($token)) {
            return $this->unauthenticated();
        }

        $key = ApiKey::forToken($token)->active()->with('admin')->first();

        if ($key === null) {
            return $this->unauthenticated();
        }

        try {
            $this->rateLimit->check(RateLimitName::McpRequest, $key->id);
        } catch (RateLimitExceeded $exceeded) {
            return response()
                ->json(['error' => 'rate limited', 'retry_after_seconds' => $exceeded->retryAfterSeconds], 429)
                ->header('Retry-After', (string) $exceeded->retryAfterSeconds);
        }

        $request->attributes->set(LogMcpCall::KEY_ATTRIBUTE, $key->id);
        $admin = $key->admin;

        if ($admin === null) {
            return $this->unauthenticated();
        }

        Auth::shouldUse(ActorType::Admin->guard());
        Auth::guard(ActorType::Admin->guard())->setUser($admin);
        Story::actorIs(ActorType::Admin, $admin->id);

        $key->markUsed(now()->toDateTimeImmutable());

        return $next($request);
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json(['error' => 'unauthenticated'], 401);
    }
}
