{{--
    The four states the seller listings screen reads a listing's status as
    (Listings.dc.html): Live (for sale), Draft, Sold out, or Removed. An
    active admin removal outranks the listing's own status the same way it
    does everywhere else a listing's availability is judged
    (App\Domain\Listings\ListingAvailability) — an archived listing reads
    the same way, since the seller took it down themselves. Requires
    `activeRemoval` eager-loaded on `$listing`, same as the list pane query.
    Renders through x-seller.status-badge, the same pill every other
    seller-portal status reads through, so a listing's badge never reads a
    different size than its own row's status cell in the listings table.
--}}
@props(['listing'])

@php
    $hasActiveRemoval = (bool) $listing->activeRemoval;
@endphp

<x-seller.status-badge {{ $attributes }} :tint="$listing->status->sellerBadgeTint($hasActiveRemoval)">{{ $listing->status->sellerBadgeLabel($hasActiveRemoval) }}</x-seller.status-badge>
