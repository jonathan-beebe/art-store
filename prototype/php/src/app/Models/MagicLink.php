<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\MagicLinkStatus;
use App\Domain\Auth\MagicLinkToken;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\MagicLinkFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

#[Fillable(['token_hash', 'email', 'actor_type', 'redirect_to', 'expires_at'])]
#[Hidden(['token_hash'])]
class MagicLink extends Model
{
    /** @use HasFactory<MagicLinkFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'mlk';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function forToken(Builder $query, string $token): void
    {
        $query->where('token_hash', MagicLinkToken::hash($token));
    }

    public function statusAt(DateTimeImmutable $now): MagicLinkStatus
    {
        return MagicLinkStatus::of($this->expires_at, $this->consumed_at, $now);
    }

    /**
     * Claims the link for one verification, and says whether this caller is
     * the one that got it. `consumed_at is null` is part of the write rather
     * than a read taken before it, so of two verifications racing on the same
     * token the database hands one an affected row and the other none — a
     * link read as usable a moment ago is not a link still usable now.
     */
    public function consume(DateTimeImmutable $now): bool
    {
        $claimed = $this->newQuery()->whereKey($this->getKey())->whereNull('consumed_at')->update([
            'consumed_at' => $now,
        ]) === 1;

        if ($claimed) {
            $this->forceFill(['consumed_at' => $now])->syncOriginal();
        }

        return $claimed;
    }
}
