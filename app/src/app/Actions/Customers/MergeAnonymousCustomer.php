<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Analytics\Analytics;
use App\Domain\Customers\CustomerCartLine;
use App\Domain\Customers\CustomerMergePlan;
use App\Domain\Customers\CustomerOwnedTables;
use App\Logging\StoryEvent;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Models\Favorite;
use App\Models\Listing;
use App\Support\Story;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class MergeAnonymousCustomer
{
    public function __construct(private Analytics $analytics) {}

    /**
     * @return Customer the verified customer, now holding both histories
     */
    public function __invoke(Customer $anonymous, Customer $verified): Customer
    {
        return Story::for(StoryEvent::CustomerMerge)->tell('folding an anonymous customer into a verified one', [
            'anonymous_customer_id' => $anonymous->id,
            'customer_id' => $verified->id,
        ], function (Story $story) use ($anonymous, $verified): Customer {
            $plan = DB::transaction(function () use ($anonymous, $verified): CustomerMergePlan {
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

                $plan = $this->planMerge($anonymous, $verified);
                $this->foldCart($anonymous, $verified, $plan);
                $this->foldFavorites($anonymous, $verified, $plan);

                // The anonymous row survives the merge, so a cookie still holding its
                // id resolves forward to the verified customer.
                // `anonymous_customer_id` is unique, so merging the same anonymous
                // customer again finds the row it already wrote.
                CustomerMerge::firstOrCreate(
                    ['anonymous_customer_id' => $anonymous->id],
                    ['customer_id' => $verified->id],
                );

                return $plan;
            });

            $this->analytics->reassignActor($anonymous->id, $verified->id);

            $story->did('folded the anonymous customer in', [
                'anonymous_customer_id' => $anonymous->id,
                'customer_id' => $verified->id,
                'cart_line_count' => count($plan->cartLines),
                'favorites_moved' => count($plan->favoritesToMove),
                'favorites_dropped' => count($plan->favoritesToDrop),
            ]);

            return $verified;
        });
    }

    /**
     * Reads both customers' cart items, favorites, and the stock behind
     * whatever listings either cart names, and folds them into a plan —
     * nothing is written here.
     */
    private function planMerge(Customer $anonymous, Customer $verified): CustomerMergePlan
    {
        $verifiedLines = $this->cartLinesFor($verified);
        $anonymousLines = $this->cartLinesFor($anonymous);

        /** @var list<string> $listingIds */
        $listingIds = collect([...$verifiedLines, ...$anonymousLines])
            ->map(fn (CustomerCartLine $line): string => $line->listingId)
            ->unique()
            ->values()
            ->all();

        /** @var array<string, int|null> $stockByListing */
        $stockByListing = Listing::query()->whereIn('id', $listingIds)->pluck('quantity', 'id')->all();

        return CustomerMergePlan::for(
            verifiedCartLines: $verifiedLines,
            anonymousCartLines: $anonymousLines,
            verifiedFavoriteListingIds: $this->favoriteListingIdsFor($verified),
            anonymousFavoriteListingIds: $this->favoriteListingIdsFor($anonymous),
            stockByListing: $stockByListing,
        );
    }

    /**
     * @return list<CustomerCartLine>
     */
    private function cartLinesFor(Customer $customer): array
    {
        $lines = CartItem::query()
            ->whereIn('cart_id', $customer->carts()->pluck('id'))
            ->get(['listing_id', 'quantity', 'variant_id', 'unit_id', 'configuration_json', 'answers_json', 'fingerprint'])
            ->map(fn (CartItem $item): CustomerCartLine => new CustomerCartLine(
                $item->listing_id,
                $item->quantity,
                $item->fingerprint,
                $item->variant_id,
                $item->unit_id,
                $item->configuration_json,
                $item->answers_json,
            ))
            ->values()
            ->all();

        /** @var list<CustomerCartLine> $lines */
        return $lines;
    }

    /**
     * @return list<string>
     */
    private function favoriteListingIdsFor(Customer $customer): array
    {
        /** @var list<string> $ids */
        $ids = $customer->favorites()->pluck('listing_id')->values()->all();

        return $ids;
    }

    /**
     * Leaves the verified customer with exactly one cart, holding the plan's
     * folded lines. Every other cart either customer held, and whatever it
     * contained, is gone — re-pointing `carts.customer_id` the way
     * `CustomerOwnedTables` does for a simple table would leave the verified
     * customer with two. The stock a clamp is judged against is read without
     * a lock, the same as `AddToCart` reads it: nothing here decrements
     * stock, and the cart page recomputes `placementPlan()` fresh on every
     * read, so a line a race left briefly over stock is caught there, the
     * same as a listing that sells out while already sitting in a cart.
     */
    private function foldCart(Customer $anonymous, Customer $verified, CustomerMergePlan $plan): void
    {
        $survivor = $verified->carts()->first();

        if ($survivor === null) {
            $survivor = $anonymous->carts()->first();

            if ($survivor !== null) {
                $survivor->update(['customer_id' => $verified->id]);
            } else {
                $survivor = $verified->carts()->create();
            }
        }

        Cart::query()
            ->whereIn('customer_id', [$anonymous->id, $verified->id])
            ->where('id', '!=', $survivor->id)
            ->delete();

        $survivor->items()->delete();

        foreach ($plan->cartLines as $line) {
            $survivor->items()->create([
                'customer_id' => $survivor->customer_id,
                'listing_id' => $line->listingId,
                'quantity' => $line->quantity,
                'variant_id' => $line->variantId,
                'unit_id' => $line->unitId,
                'configuration_json' => $line->configurationJson,
                'answers_json' => $line->answersJson,
                'fingerprint' => $line->fingerprint,
            ]);
        }
    }

    /**
     * Applies the favorites half of the plan with updates and deletes only,
     * never an insert. This keeps the same discipline the table-driven
     * re-point above holds. It needs no knowledge of the `favorites` table
     * beyond the two columns the plan already judged.
     */
    private function foldFavorites(Customer $anonymous, Customer $verified, CustomerMergePlan $plan): void
    {
        if ($plan->favoritesToMove !== []) {
            Favorite::query()
                ->where('customer_id', $anonymous->id)
                ->whereIn('listing_id', $plan->favoritesToMove)
                ->update(['customer_id' => $verified->id]);
        }

        if ($plan->favoritesToDrop !== []) {
            Favorite::query()
                ->where('customer_id', $anonymous->id)
                ->whereIn('listing_id', $plan->favoritesToDrop)
                ->delete();
        }
    }
}
