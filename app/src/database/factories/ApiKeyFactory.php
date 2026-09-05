<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Auth\ApiKeyToken;
use App\Models\Admin;
use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'admin_id' => Admin::factory(),
            'name' => 'Claude Code',
            'token_hash' => ApiKeyToken::hash(ApiKeyToken::PREFIX.Str::random(ApiKeyToken::SECRET_LENGTH)),
        ];
    }

    public function forToken(string $token): static
    {
        return $this->state(fn (array $attributes): array => ['token_hash' => ApiKeyToken::hash($token)]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => ['revoked_at' => now()->subDay()]);
    }
}
