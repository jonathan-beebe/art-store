<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\StoreSlugFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One address a store has answered to. The row with a null `retired_at` is
 * the current address, the same string the profile carries; every other row
 * is one a rename left behind, and the storefront redirects from it while
 * it is young enough.
 */
#[Fillable(['store_profile_id', 'slug', 'retired_at'])]
class StoreSlug extends Model
{
    /** @use HasFactory<StoreSlugFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'ssl';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return ['retired_at' => 'datetime'];
    }

    /** @return BelongsTo<StoreProfile, $this> */
    public function storeProfile(): BelongsTo
    {
        return $this->belongsTo(StoreProfile::class);
    }

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereNull('retired_at');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function retired(Builder $query): void
    {
        $query->whereNotNull('retired_at');
    }
}
