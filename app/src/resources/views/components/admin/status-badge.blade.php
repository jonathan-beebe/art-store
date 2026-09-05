{{-- The small state pill a list-pane cell's second line leads with
     (DSGN-006). `tint` is a caller-chosen semantic (ok/warn/bad), not the
     status value itself — each section maps its own enum to one of these
     three, or leaves the default neutral gray for a status with no
     good/bad reading (shipped, sold, archived). --}}
@props(['tint' => 'neutral'])

@php
    $classes = match ($tint) {
        'ok' => 'bg-green-50 text-green-800 dark:bg-green-950/40 dark:text-green-300',
        'warn' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/30 dark:text-amber-300',
        'bad' => 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-300',
        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium '.$classes]) }}>{{ $slot }}</span>
