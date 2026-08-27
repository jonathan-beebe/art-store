<?php

declare(strict_types=1);

namespace App\Actions\Configurator;

use App\Domain\Configurator\ConfiguratorDeletionGuard;
use App\Logging\StoryEvent;
use App\Models\OptionAxis;
use App\Support\Story;

final readonly class DeleteOptionAxis
{
    public function __invoke(OptionAxis $axis): void
    {
        Story::for(StoryEvent::ListingUpdate)->tell('deleting an option axis', [
            'listing_id' => $axis->listing_id,
            'axis_id' => $axis->id,
        ], function (Story $story) use ($axis): void {
            ConfiguratorDeletionGuard::forAxis($axis->optionValues()->whereHas('variantOptions')->exists());

            $axis->delete();

            $story->did('deleted the option axis', [
                'listing_id' => $axis->listing_id,
                'axis_id' => $axis->id,
            ]);
        });
    }
}
