<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ApiKeyToken;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\ApiKeyFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * An admin's MCP api key (docs/spec.md §5 "MCP endpoint"): the digest of
 * a token shown once, the name the admin gave it, when it was last
 * presented, and when it was revoked. A revoked key stays as a record of
 * its existence and never authenticates again.
 */
#[Fillable(['admin_id', 'name', 'token_hash'])]
#[Hidden(['token_hash'])]
class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    /**
     * `last_used_at` is written at most this often. A burst of tool calls
     * costs at most one UPDATE.
     */
    public const int USED_AT_GRAIN_SECONDS = 60;

    public static function idPrefix(): string
    {
        return 'key';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function forToken(Builder $query, string $token): void
    {
        $query->where('token_hash', ApiKeyToken::hash($token));
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(DateTimeImmutable $now): void
    {
        if ($this->isRevoked()) {
            return;
        }

        $this->forceFill(['revoked_at' => $now])->save();
    }

    /**
     * Records that the key was presented, at most once per
     * `USED_AT_GRAIN_SECONDS`.
     */
    public function markUsed(DateTimeImmutable $now): void
    {
        $lastUsed = $this->last_used_at;

        if ($lastUsed !== null && $now->getTimestamp() - $lastUsed->getTimestamp() < self::USED_AT_GRAIN_SECONDS) {
            return;
        }

        $this->forceFill(['last_used_at' => $now])->save();
    }
}
