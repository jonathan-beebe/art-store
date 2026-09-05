<?php

declare(strict_types=1);

namespace App\Actions\Store;

use App\Domain\Store\StoreDraft;
use App\Domain\Store\StoreLinkKind;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\StoreProfile;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The Store screen's save: identity, address, visibility, and the links
 * under the story, in one transaction. A store published for the first time
 * takes `$now` as its published stamp; one already published keeps the
 * stamp it has, so hiding and publishing again does not rewrite when it
 * first opened.
 */
final readonly class SaveStore
{
    public function __construct(private RenameStoreSlug $renameStoreSlug) {}

    public function __invoke(StoreProfile $profile, StoreDraft $draft, DateTimeImmutable $now): StoreProfile
    {
        return Story::for(StoryEvent::StoreSave)->tell('saving a store profile', [
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
        ], function (Story $story) use ($profile, $draft, $now): StoreProfile {
            $saved = DB::transaction(function () use ($profile, $draft, $now): StoreProfile {
                ($this->renameStoreSlug)($profile, $draft->slug, $now);

                $profile->update($draft->attributes() + [
                    'published_at' => $draft->visibility->isPublished()
                        ? $profile->published_at ?? $now
                        : null,
                ]);

                $this->syncLinks($profile, $draft);

                return $profile;
            });

            $story->did('saved the store profile', [
                'seller_id' => $saved->seller_id,
                'store_profile_id' => $saved->id,
            ]);

            return $saved;
        });
    }

    /**
     * The kinds the seller filled in keep or take a row; every other kind
     * loses the one it had.
     */
    private function syncLinks(StoreProfile $profile, StoreDraft $draft): void
    {
        $kept = [];

        foreach ($draft->orderedLinks() as $link) {
            $profile->links()->updateOrCreate(
                ['kind' => $link['kind']],
                ['url' => $link['url'], 'position' => $link['position']],
            );

            $kept[] = $link['kind']->value;
        }

        $dropped = array_values(array_diff(array_column(StoreLinkKind::cases(), 'value'), $kept));

        if ($dropped !== []) {
            $profile->links()->whereIn('kind', $dropped)->delete();
        }
    }
}
