<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\DomainRuleViolation;
use App\Logging\StoryEvent;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Support\Story;
use Illuminate\Support\Facades\DB;

final readonly class BlockCustomer
{
    public function __invoke(Customer $customer, string $reason): CustomerBlock
    {
        return Story::for(StoryEvent::ModerationBlockCustomer)->tell('blocking a customer', [
            'customer_id' => $customer->id,
        ], function (Story $story) use ($customer, $reason): CustomerBlock {
            $block = DB::transaction(function () use ($customer, $reason): CustomerBlock {
                // Judged inside the transaction that writes, against a row
                // held for update: two admins blocking the same customer at
                // once are held apart by the row they both take, so the
                // second reads the block the first wrote and is refused. The
                // table cannot say so on its own — SQLite has no partial
                // unique index — which leaves this rule the only thing
                // holding a customer to one active block.
                if (! $customer->takeForModeration()->canShop()) {
                    throw new DomainRuleViolation('This customer is already blocked.');
                }

                return $customer->blocks()->create([
                    'reason' => $reason,
                    'lifted_at' => null,
                ]);
            });

            $story->did('blocked the customer', [
                'customer_id' => $customer->id,
                'customer_block_id' => $block->id,
                'reason' => $reason,
            ]);

            return $block;
        });
    }
}
