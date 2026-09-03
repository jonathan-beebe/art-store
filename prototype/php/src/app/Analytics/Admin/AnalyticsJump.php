<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\JumpKind;
use App\Models\Customer;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;

/**
 * Reads a pasted search string as a jump straight to one listing or actor:
 * a `lst_`/`cus_` id prefix, or an ip that every event in the store agrees
 * belongs to one actor. Anything shorter than a real prefix, or that names
 * more than one row, is not a jump — the entry page's search stays a plain
 * filter for it instead.
 */
final class AnalyticsJump
{
    /** The shortest prefix worth resolving — a shorter one would routinely
     * name more than one row. */
    private const int MIN_ID_LENGTH = 6;

    public static function for(string $query): ?Jump
    {
        $q = trim($query);

        if ($q === '') {
            return null;
        }

        return self::matchListingId($q) ?? self::matchCustomerId($q) ?? self::matchIp($q);
    }

    private static function matchListingId(string $q): ?Jump
    {
        if (! self::looksLikePrefix($q, Listing::idPrefix())) {
            return null;
        }

        $matches = Listing::query()->where('id', 'like', $q.'%')->limit(2)->get(['id', 'title']);

        if ($matches->count() !== 1) {
            return null;
        }

        $listing = $matches->first();

        if (! $listing instanceof Listing) {
            return null;
        }

        return new Jump($listing->id, "listing · {$listing->title}", JumpKind::Listing);
    }

    private static function matchCustomerId(string $q): ?Jump
    {
        if (! self::looksLikePrefix($q, Customer::idPrefix())) {
            return null;
        }

        $matches = Customer::query()->where('id', 'like', $q.'%')->limit(2)->get();

        if ($matches->count() !== 1) {
            return null;
        }

        $customer = $matches->first();

        return $customer instanceof Customer ? self::actorJump($customer) : null;
    }

    private static function matchIp(string $q): ?Jump
    {
        $actorIds = DB::connection('analytics')->table('analytics_events')
            ->where('ip', $q)
            ->whereNotNull('actor_id')
            ->distinct()
            ->pluck('actor_id');

        if ($actorIds->count() !== 1) {
            return null;
        }

        $customer = Customer::query()->find($actorIds->first());

        return $customer instanceof Customer ? self::actorJump($customer) : null;
    }

    private static function actorJump(Customer $customer): Jump
    {
        $identity = ActorIdentity::of($customer);

        return new Jump($customer->id, "{$identity->kind} customer · {$identity->who}", JumpKind::Actor);
    }

    private static function looksLikePrefix(string $q, string $prefix): bool
    {
        return strlen($q) >= self::MIN_ID_LENGTH && str_starts_with($q, $prefix.'_');
    }
}
