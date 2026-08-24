<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\DomainRuleViolation;
use App\Models\Customer;
use App\Models\CustomerBlock;
use DateTimeImmutable;

final readonly class LiftCustomerBlock
{
    public function __invoke(Customer $customer, DateTimeImmutable $now): CustomerBlock
    {
        $block = $customer->currentBlock();

        if ($block === null) {
            throw new DomainRuleViolation('This customer is not blocked.');
        }

        $block->lift($now);

        return $block;
    }
}
