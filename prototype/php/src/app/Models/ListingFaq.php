<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\ListingFaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * A published answer to a shopper's question about a listing. The row exists
 * only while it is published — there is no draft state — so `published_at`
 * is never null and unpublishing deletes the row rather than clearing it.
 *
 * @property-read Listing $listing
 */
#[Fillable(['listing_id', 'question', 'answer', 'source_message_id', 'published_at'])]
class ListingFaq extends Model
{
    /** @use HasFactory<ListingFaqFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'faq';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<Message, $this> */
    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'source_message_id');
    }
}
