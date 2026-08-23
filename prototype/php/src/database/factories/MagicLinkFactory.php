<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\MagicLinkToken;
use App\Models\MagicLink;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<MagicLink>
 */
class MagicLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'token_hash' => MagicLinkToken::hash(Str::random(40)),
            'email' => fake()->unique()->safeEmail(),
            'actor_type' => ActorType::Customer,
            'redirect_to' => null,
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subMinute()]);
    }

    /**
     * `consumed_at` is not mass-assignable, so consuming is done through the
     * model's own `consume()` after the row exists, the same as production.
     */
    public function consumed(): static
    {
        return $this->afterCreating(function (MagicLink $link): void {
            $link->consume(new DateTimeImmutable);
        });
    }
}
