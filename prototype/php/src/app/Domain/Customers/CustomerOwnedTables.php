<?php

declare(strict_types=1);

namespace App\Domain\Customers;

final class CustomerOwnedTables
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Tables whose rows move with the customer when an anonymous identity is
     * merged into a verified one by writing one column and nothing else.
     * Every other table carrying a `customer_id` column needs more than that
     * — `leftBehind()` names each one and why, and the manifest test in
     * `CustomerOwnedTablesTest` checks that the two lists together cover the
     * schema, so a new `customer_id` column cannot go unhandled by accident.
     *
     * @return array<string, string> table name => column holding the customer id
     */
    public static function all(): array
    {
        return [
            'orders' => 'customer_id',
            'order_items' => 'customer_id',
            'fulfillments' => 'customer_id',
            'payments' => 'customer_id',
            'refunds' => 'customer_id',
            'customer_blocks' => 'customer_id',
        ];
    }

    /**
     * Tables carrying a `customer_id` column that a blind write would get
     * wrong, and what handles each one instead.
     *
     * @return array<string, string> table name => why it is not in `all()`
     */
    public static function leftBehind(): array
    {
        return [
            'analytics_events' => 'lives in the analytics store, outside this transaction, keyed by actor_id rather than customer_id — MergeAnonymousCustomer re-points it separately, through Analytics::reassignActor(), which cannot fail the merge if the store is unavailable',
            'favorites' => 'folded by CustomerMergePlan — the union of both customers\' favorites, de-duplicated, applied with updates and deletes',
            'carts' => 'folded by CustomerMergePlan — quantities summed per listing and clamped to stock, applied to the one cart that survives the merge',
            'cart_items' => 'recreated by MergeAnonymousCustomer::foldCart() from the folded cart plan, which sets customer_id to the surviving cart\'s owner directly',
            'conversations' => 'moved by Conversation::moveCustomer(), which carries subject_key along with customer_id',
            'customer_merges' => 'the merge record itself — anonymous_customer_id names the row being merged, customer_id the survivor, and a merge does not rewrite its own trail',
        ];
    }
}
