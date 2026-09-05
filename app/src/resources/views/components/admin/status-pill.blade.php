{{--
    The commerce pane's status pill (Orders, Fulfillments, Listings):
    `tint` is a caller-chosen semantic color, not the status value itself,
    matching the seller portal's own x-seller.status-badge classes verbatim
    so a status reads the same badge on either side of the marketplace.
--}}
@props(['tint' => 'gray'])

@php
    $classes = match ($tint) {
        'yellow' => 'bg-yellow-50 text-yellow-800 inset-ring inset-ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-500 dark:inset-ring-yellow-400/20',
        'blue' => 'bg-blue-50 text-blue-700 inset-ring inset-ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400 dark:inset-ring-blue-400/30',
        'green' => 'bg-green-50 text-green-700 inset-ring inset-ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:inset-ring-green-500/20',
        'red' => 'bg-red-50 text-red-700 inset-ring inset-ring-red-600/10 dark:bg-red-400/10 dark:text-red-400 dark:inset-ring-red-400/20',
        default => 'bg-gray-50 text-gray-600 inset-ring inset-ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:inset-ring-gray-400/20',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-xs font-medium '.$classes]) }}>{{ $slot }}</span>
