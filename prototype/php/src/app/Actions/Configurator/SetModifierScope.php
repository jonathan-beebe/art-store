<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Logging\StoryEvent;
use App\Models\Modifier;
use App\Models\ModifierScope;
use App\Models\OptionValue;
use App\Support\Story;

/**
 * The scope picker's "show this question only when…" screen: replaces a
 * modifier's whole scope with the option values just checked, rather than
 * adding to it — an unchecked value stops gating the modifier instead of
 * gating it forever. An empty selection clears every scope, so the modifier
 * shows for every configuration, same as a modifier that was never scoped.
 */
final readonly class SetModifierScope
{
    /**
     * @param  list<OptionValue>  $optionValues
     * @return list<ModifierScope>
     */
    public function __invoke(Modifier $modifier, array $optionValues): array
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('setting a modifier’s scope', [
            'listing_id' => $modifier->listing_id,
            'modifier_id' => $modifier->id,
        ], function (Story $story) use ($modifier, $optionValues): array {
            $modifier->scopes()->whereNotIn('option_value_id', array_map(fn (OptionValue $value): string => $value->id, $optionValues))->delete();

            $scopes = array_map(
                fn (OptionValue $value): ModifierScope => $modifier->scopes()->firstOrCreate(['option_value_id' => $value->id]),
                $optionValues,
            );

            $story->did('set the modifier’s scope', [
                'listing_id' => $modifier->listing_id,
                'modifier_id' => $modifier->id,
                'option_value_ids' => array_map(fn (OptionValue $value): string => $value->id, $optionValues),
            ]);

            return $scopes;
        });
    }
}
