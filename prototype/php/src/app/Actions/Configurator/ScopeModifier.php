<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\Modifier;
use App\Models\ModifierScope;
use App\Models\OptionValue;
use App\Support\Story;

/**
 * "Show this question when…" — the primitive whose absence produced the
 * blank-mug's always-shown personalization box. Additive and idempotent: a
 * value already scoped is left alone.
 */
final readonly class ScopeModifier
{
    /**
     * @param  list<OptionValue>  $optionValues
     * @return list<ModifierScope>
     */
    public function __invoke(Modifier $modifier, array $optionValues): array
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('scoping a modifier', [
            'listing_id' => $modifier->listing_id,
            'modifier_id' => $modifier->id,
        ], function (Story $story) use ($modifier, $optionValues): array {
            $scopes = array_map(
                fn (OptionValue $value): ModifierScope => $modifier->scopes()->firstOrCreate(['option_value_id' => $value->id]),
                $optionValues,
            );

            $story->did('scoped the modifier', [
                'listing_id' => $modifier->listing_id,
                'modifier_id' => $modifier->id,
                'option_value_ids' => array_map(fn (OptionValue $value): string => $value->id, $optionValues),
            ]);

            return $scopes;
        });
    }
}
