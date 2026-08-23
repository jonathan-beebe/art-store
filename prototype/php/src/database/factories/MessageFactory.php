<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        $sender = Customer::factory()->create();

        return [
            'conversation_id' => Conversation::factory(),
            'sender_type' => $sender->getMorphClass(),
            'sender_id' => $sender->id,
            'body' => fake()->realText(200),
            'sent_at' => now(),
            'read_at' => null,
        ];
    }

    public function from(Seller|Customer|Admin $sender): static
    {
        return $this->state(fn (): array => [
            'sender_type' => $sender->getMorphClass(),
            'sender_id' => $sender->id,
        ]);
    }

    public function unread(): static
    {
        return $this->state(fn (): array => ['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
