<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\DomainRuleViolation;
use App\Models\Customer;
use App\Models\CustomerBlock;

final readonly class BlockCustomer
{
    public function __invoke(Customer $customer, string $reason): CustomerBlock
    {
        if (! $customer->canShop()) {
            throw new DomainRuleViolation('This customer is already blocked.');
        }

        return $customer->blocks()->create([
            'reason' => $reason,
            'lifted_at' => null,
        ]);
    }
}
