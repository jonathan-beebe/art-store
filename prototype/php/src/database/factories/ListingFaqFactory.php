<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingFaq;
use App\Models\Message;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<ListingFaq>
 */
class ListingFaqFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'seller_id' => Seller::factory(),
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'source_message_id' => null,
            'published_at' => now(),
        ];
    }

    public function fromMessage(Message $message): static
    {
        return $this->state(fn (): array => ['source_message_id' => $message->id]);
    }
}
