<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\ConversationSubject;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function definition(): array
    {
        return $this->listingQuestionAttributes();
    }

    public function adminSeller(): static
    {
        return $this->state(function (): array {
            $admin = Admin::factory()->create();
            $seller = Seller::factory()->create();

            return $this->columnsFor(ConversationSubject::adminSeller($admin->id, $seller->id));
        });
    }

    public function adminCustomer(): static
    {
        return $this->state(function (): array {
            $admin = Admin::factory()->create();
            $customer = Customer::factory()->create();

            return $this->columnsFor(ConversationSubject::adminCustomer($admin->id, $customer->id));
        });
    }

    public function fulfillment(): static
    {
        return $this->state(function (): array {
            $customer = Customer::factory()->create();
            $order = Order::factory()->create(['customer_id' => $customer->id]);
            $seller = Seller::factory()->create();
            $fulfillment = Fulfillment::factory()->create(['order_id' => $order->id, 'seller_id' => $seller->id]);

            return $this->columnsFor(ConversationSubject::fulfillment($seller->id, $customer->id, $fulfillment->id));
        });
    }

    public function listingQuestion(): static
    {
        return $this->state(fn (): array => $this->listingQuestionAttributes());
    }

    /**
     * Builds the row a given subject already names, so a test that overrides
     * a participant column (`->forSubject($subject)->create(['seller_id' =>
     * $seller->id])` with the subject built from that same seller) writes a
     * `subject_key` that agrees with the columns rather than one a later
     * override contradicts.
     */
    public function forSubject(ConversationSubject $subject): static
    {
        return $this->state(fn (): array => $this->columnsFor($subject));
    }

    /**
     * @return array<string, mixed>
     */
    private function listingQuestionAttributes(): array
    {
        $seller = Seller::factory()->create();
        $customer = Customer::factory()->create();
        $listing = Listing::factory()->create(['seller_id' => $seller->id]);

        return $this->columnsFor(ConversationSubject::listingQuestion($seller->id, $customer->id, $listing->id));
    }

    /**
     * Every participant and subject column, nulled out before the given
     * subject's own values are laid over them — the shape a real conversation
     * row holds, regardless of which kind a state builds.
     *
     * @return array<string, mixed>
     */
    private function columnsFor(ConversationSubject $subject): array
    {
        return $subject->columns() + [
            'subject_key' => $subject->subjectKey(),
            'seller_id' => null,
            'customer_id' => null,
            'admin_id' => null,
            'listing_id' => null,
            'fulfillment_id' => null,
            'last_message_at' => now(),
        ];
    }
}
