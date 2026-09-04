<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\DomainRuleViolation;
use App\Models\FulfillmentFlow;

/**
 * Removes a flow no listing ships by and that carries no default role — the
 * two guards a seller's delete button has to pass, checked against the row
 * as it stands at delete time.
 */
final readonly class DeleteFulfillmentFlow
{
    public function __invoke(FulfillmentFlow $flow): void
    {
        if ($flow->is_default) {
            throw new DomainRuleViolation('The default workflow ships every listing that names none. Make another workflow the default first.');
        }

        if ($flow->listings()->exists()) {
            throw new DomainRuleViolation('A listing ships by this workflow. Point it to another workflow first.');
        }

        $flow->delete();
    }
}
