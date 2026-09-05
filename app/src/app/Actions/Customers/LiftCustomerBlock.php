<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\DomainRuleViolation;
use App\Logging\Story;
use App\Logging\StoryEvent;
use App\Models\Customer;
use App\Models\CustomerBlock;
use DateTimeImmutable;

final readonly class LiftCustomerBlock
{
    public function __invoke(Customer $customer, DateTimeImmutable $now): CustomerBlock
    {
        return Story::for(StoryEvent::ModerationLiftCustomerBlock)->tell('lifting a customer block', [
            'customer_id' => $customer->id,
        ], function (Story $story) use ($customer, $now): CustomerBlock {
            $block = $customer->currentBlock()
                ?? throw new DomainRuleViolation('This customer is not blocked.');

            $block->lift($now);

            $story->did('lifted the customer block', [
                'customer_id' => $customer->id,
                'customer_block_id' => $block->id,
            ]);

            return $block;
        });
    }
}
