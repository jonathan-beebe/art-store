<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ComboKey;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Listing;
use App\Models\OptionAxis;
use App\Models\OptionValue;
use App\Models\Variant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The full cross product of a listing's option axes, one variant per
 * combination — the grid a seller lacking any reason to go sparse fills in
 * one step. Idempotent: a combination that already has a variant is left
 * alone, so calling this again after hand-adding one sparse cell fills in
 * the rest without duplicating it.
 */
final readonly class GenerateVariants
{
    /**
     * @return list<Variant>
     */
    public function __invoke(Listing $listing): array
    {
        return Story::for(StoryEvent::ListingUpdate)->tell('generating variants from option axes', [
            'listing_id' => $listing->id,
        ], function (Story $story) use ($listing): array {
            return DB::transaction(function () use ($story, $listing): array {
                $axes = $listing->optionAxes()->with('optionValues')->orderBy('position')->get();

                // Zero axes has no combination to generate: the legacy, axis-free
                // path keeps its zero rows. It never gains one variant for the
                // empty combo key.
                if ($axes->isEmpty()) {
                    $story->did('no axes to generate variants from', [
                        'listing_id' => $listing->id,
                        'created_count' => 0,
                    ]);

                    return [];
                }

                $existingComboKeys = $listing->variants()->pluck('combo_key')->all();
                $created = [];

                foreach ($this->combinations($axes) as $combination) {
                    $comboKey = ComboKey::of(array_map(fn (OptionValue $value): string => $value->id, $combination));

                    if (in_array($comboKey->value, $existingComboKeys, true)) {
                        continue;
                    }

                    $variant = $listing->variants()->create(['seller_id' => $listing->seller_id, 'combo_key' => $comboKey->value]);

                    foreach ($axes as $index => $axis) {
                        $variant->options()->create([
                            'seller_id' => $variant->seller_id,
                            'axis_id' => $axis->id,
                            'option_value_id' => $combination[$index]->id,
                        ]);
                    }

                    $existingComboKeys[] = $comboKey->value;
                    $created[] = $variant;
                }

                $story->did('generated variants from option axes', [
                    'listing_id' => $listing->id,
                    'created_count' => count($created),
                ]);

                return $created;
            });
        });
    }

    /**
     * @param  Collection<int, OptionAxis>  $axes
     * @return list<list<OptionValue>>
     */
    private function combinations(Collection $axes): array
    {
        $combinations = [[]];

        foreach ($axes as $axis) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($axis->optionValues as $value) {
                    $next[] = [...$combination, $value];
                }
            }

            $combinations = $next;
        }

        return $combinations;
    }
}
