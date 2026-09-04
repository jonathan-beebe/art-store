<?php

declare(strict_types=1);

namespace App\Admin;

use App\Domain\Escrow\PlatformFees;
use App\Models\Fulfillment;

/**
 * What the admin dashboard and the accounting page read across every
 * fulfillment on the platform: how many hold each status, and what the
 * platform earned and gave back in fees.
 */
final readonly class PlatformFulfillmentReader
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, int> status value => count
     */
    public static function countsByStatus(): array
    {
        $counts = [];

        foreach (Fulfillment::query()->countedByStatus()->get() as $row) {
            $counts[$row->status->value] = $row->tally;
        }

        return $counts;
    }

    /**
     * Folded by the pure {@see PlatformFees} from one read of every
     * fulfillment's status and fee.
     */
    public static function fees(): PlatformFees
    {
        return PlatformFees::from(array_values(
            Fulfillment::query()
                ->select('status', 'fee_cents')
                ->get()
                ->map(fn (Fulfillment $fulfillment): array => [
                    'status' => $fulfillment->status,
                    'feeCents' => $fulfillment->fee_cents,
                ])
                ->all(),
        ));
    }
}
