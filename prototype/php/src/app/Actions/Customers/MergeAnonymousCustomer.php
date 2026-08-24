<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Domain\Customers\CustomerOwnedTables;
use App\Logging\StoryEvent;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Support\Story;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class MergeAnonymousCustomer
{
    /**
     * @return Customer the verified customer, now holding both histories
     */
    public function __invoke(Customer $anonymous, Customer $verified): Customer
    {
        return Story::for(StoryEvent::CustomerMerge)->tell('folding an anonymous customer into a verified one', [
            'anonymous_customer_id' => $anonymous->id,
            'customer_id' => $verified->id,
        ], function (Story $story) use ($anonymous, $verified): Customer {
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

                // A conversation carries its participants in `subject_key` as
                // well as in a column, so the two move together and the verified
                // customer keeps one thread per subject.
                Conversation::moveCustomer($anonymous, $verified);

                // The anonymous row survives the merge so a cookie still holding its
                // id resolves forward instead of starting the visitor over.
                // `anonymous_customer_id` is unique, so merging the same anonymous
                // customer again finds the row it already wrote instead of failing.
                CustomerMerge::firstOrCreate(
                    ['anonymous_customer_id' => $anonymous->id],
                    ['customer_id' => $verified->id],
                );
            });

            $story->did('folded the anonymous customer in', [
                'anonymous_customer_id' => $anonymous->id,
                'customer_id' => $verified->id,
            ]);

            return $verified;
        });
    }
}
