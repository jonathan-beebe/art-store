<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\MagicLinkStatus;
use App\Domain\Auth\MagicLinkToken;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['token_hash', 'email', 'actor_type', 'redirect_to', 'expires_at'])]
#[Hidden(['token_hash'])]
class MagicLink extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForToken(Builder $query, string $token): Builder
    {
        return $query->where('token_hash', MagicLinkToken::hash($token));
    }

    public function statusAt(Carbon $now): MagicLinkStatus
    {
        return MagicLinkStatus::of($this->expires_at, $this->consumed_at, $now);
    }

    public function consume(Carbon $now): void
    {
        $this->forceFill(['consumed_at' => $now])->save();
    }
}
