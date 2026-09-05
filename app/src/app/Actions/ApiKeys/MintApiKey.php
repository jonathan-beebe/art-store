<?php

declare(strict_types=1);

namespace App\Actions\ApiKeys;

use App\Domain\Auth\ApiKeyToken;
use App\Models\Admin;
use App\Models\ApiKey;
use App\Support\ApiKeys\MintedApiKey;
use Illuminate\Support\Str;

/**
 * Mints one MCP api key for an admin (docs/spec.md §5 "MCP endpoint"):
 * a random secret behind the `artstore_` prefix, stored as its digest.
 * The plaintext leaves this action exactly once, in the return value.
 */
final readonly class MintApiKey
{
    public function __invoke(Admin $admin, string $name): MintedApiKey
    {
        $plaintext = ApiKeyToken::PREFIX.Str::random(ApiKeyToken::SECRET_LENGTH);

        $key = ApiKey::create([
            'admin_id' => $admin->id,
            'name' => $name,
            'token_hash' => ApiKeyToken::hash($plaintext),
        ]);

        return new MintedApiKey($key, $plaintext);
    }
}
