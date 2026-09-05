<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\ThreadTitle;
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

    /**
     * A fresh admin/seller support thread. `admin_id` starts null, the way a
     * real one does until an admin's first reply — a test that needs one set
     * overrides it in `->create([...])`.
     */
    public function adminSeller(): static
    {
        return $this->state(fn (): array => [
            'kind' => 'admin_seller',
            'title' => null,
            'subject_key' => null,
            'seller_id' => Seller::factory(),
            'customer_id' => null,
            'admin_id' => null,
            'listing_id' => null,
            'fulfillment_id' => null,
            'order_id' => null,
            'last_message_at' => now(),
        ]);
    }

    public function adminCustomer(): static
    {
        return $this->state(fn (): array => [
            'kind' => 'admin_customer',
            'title' => null,
            'subject_key' => null,
            'seller_id' => null,
            'customer_id' => Customer::factory(),
            'admin_id' => null,
            'listing_id' => null,
            'fulfillment_id' => null,
            'order_id' => null,
            'last_message_at' => now(),
        ]);
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
     * Builds the row a given fulfillment subject already names, so a test
     * that overrides a participant column (`->forSubject($subject)->create(['seller_id' =>
     * $seller->id])` with the subject built from that same seller) writes a
     * `subject_key` that agrees with the columns, so a later override never
     * contradicts it. Fulfillment is the only kind with a `subject_key`
     * to keep consistent this way — the other three states above set their
     * own columns directly, since a fresh thread's `subject_key` is always
     * null regardless of which participant a test overrides.
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

        return [
            'kind' => 'listing_question',
            'title' => ThreadTitle::of('A question about this piece')->value,
            'subject_key' => null,
            'seller_id' => $seller->id,
            'customer_id' => $customer->id,
            'admin_id' => null,
            'listing_id' => $listing->id,
            'fulfillment_id' => null,
            'order_id' => null,
            'last_message_at' => now(),
        ];
    }

    /**
     * Every participant and context column, nulled out before the given
     * subject's own values are laid over them — the shape a real fulfillment
     * conversation row holds.
     *
     * @return array<string, mixed>
     */
    private function columnsFor(ConversationSubject $subject): array
    {
        return $subject->columns() + [
            'title' => null,
            'subject_key' => $subject->subjectKey(),
            'seller_id' => null,
            'customer_id' => null,
            'admin_id' => null,
            'listing_id' => null,
            'fulfillment_id' => null,
            'order_id' => null,
            'last_message_at' => now(),
        ];
    }
}
