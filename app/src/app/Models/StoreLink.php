<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Store\StoreLinkKind;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\StoreLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One place a store points buyers to, shown under the story. One row per
 * kind per profile; a kind the seller leaves blank has no row.
 */
#[Fillable(['store_profile_id', 'kind', 'url', 'position'])]
class StoreLink extends Model
{
    /** @use HasFactory<StoreLinkFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'slk';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => StoreLinkKind::class,
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<StoreProfile, $this> */
    public function storeProfile(): BelongsTo
    {
        return $this->belongsTo(StoreProfile::class);
    }

    /**
     * The address a browser follows. An Instagram handle is stored as the
     * seller typed it and reaches the browser as a profile URL.
     */
    public function href(): string
    {
        return $this->kind === StoreLinkKind::Instagram
            ? 'https://instagram.com/'.ltrim($this->url, '@')
            : $this->url;
    }

    /** The text the link shows. */
    public function display(): string
    {
        return $this->kind === StoreLinkKind::Instagram
            ? '@'.ltrim($this->url, '@')
            : preg_replace('#^https?://#', '', $this->url) ?? $this->url;
    }
}
