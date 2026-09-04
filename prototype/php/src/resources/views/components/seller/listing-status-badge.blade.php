{{--
    The four states the seller listings screen reads a listing's status as
    (Listings.dc.html): Live (for sale), Draft, Sold out, or Removed. An
    active admin removal outranks the listing's own status the same way it
    does everywhere else a listing's availability is judged
    (App\Domain\Listings\ListingAvailability) — an archived listing reads
    the same way, since the seller took it down themselves. Requires
    `activeRemoval` eager-loaded on `$listing`, same as the list pane query.
--}}
@props(['listing'])

@php
    $hasActiveRemoval = (bool) $listing->activeRemoval;
    $label = $listing->status->sellerBadgeLabel($hasActiveRemoval);
    $color = $listing->status->sellerBadgeTint($hasActiveRemoval);

    $colorClasses = match ($color) {
        'green' => 'bg-green-50 text-green-700 inset-ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:inset-ring-green-500/20',
        'red' => 'bg-red-50 text-red-700 inset-ring-red-600/10 dark:bg-red-400/10 dark:text-red-400 dark:inset-ring-red-400/20',
        default => 'bg-gray-50 text-gray-600 inset-ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:inset-ring-gray-400/20',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium inset-ring '.$colorClasses]) }}>{{ $label }}</span>
