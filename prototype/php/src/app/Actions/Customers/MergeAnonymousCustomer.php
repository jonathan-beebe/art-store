<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\Customers\CustomerOwnedTables;
use App\Models\Customer;
use App\Models\CustomerMerge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class MergeAnonymousCustomer
{
    /**
     * @return Customer the verified customer, now holding both histories
     */
    public function __invoke(Customer $anonymous, Customer $verified): Customer
    {
        DB::transaction(function () use ($anonymous, $verified): void {
            foreach (CustomerOwnedTables::all() as $table => $column) {
                // The commerce tables arrive on their own schedule; a merge run
                // before they exist still has to write its trail.
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)->where($column, $anonymous->id)->update([$column => $verified->id]);
            }

            // A notification names its recipient, and a message names its
            // sender, by morph type and id, so each relation re-points only
            // the rows addressed to or sent by this customer — a message the
            // verified customer sent must not read as unread to them after.
            $anonymous->notifications()->update(['notifiable_id' => $verified->id]);
            $anonymous->sentMessages()->update(['sender_id' => $verified->id]);

            // The anonymous row survives the merge so a cookie still holding its
            // id resolves forward instead of starting the visitor over.
            // `anonymous_customer_id` is unique, so merging the same anonymous
            // customer again finds the row it already wrote instead of failing.
            CustomerMerge::firstOrCreate(
                ['anonymous_customer_id' => $anonymous->id],
                ['customer_id' => $verified->id],
            );
        });

        return $verified;
    }
}
