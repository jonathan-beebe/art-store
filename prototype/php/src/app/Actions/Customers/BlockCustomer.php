<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\DomainRuleViolation;
use App\Logging\StoryEvent;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Support\Story;

final readonly class BlockCustomer
{
    public function __invoke(Customer $customer, string $reason): CustomerBlock
    {
        $story = Story::for(StoryEvent::ModerationBlockCustomer)->will('blocking a customer', [
            'customer_id' => $customer->id,
        ]);

        if (! $customer->canShop()) {
            $story->refused('This customer is already blocked.', ['customer_id' => $customer->id]);

            throw new DomainRuleViolation('This customer is already blocked.');
        }

        $block = $customer->blocks()->create([
            'reason' => $reason,
            'lifted_at' => null,
        ]);

        $story->did('blocked the customer', [
            'customer_id' => $customer->id,
            'customer_block_id' => $block->id,
            'reason' => $reason,
        ]);

        return $block;
    }
}
