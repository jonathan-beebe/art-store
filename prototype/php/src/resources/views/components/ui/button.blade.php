@props(['variant' => 'primary'])

@php
    $classes = match ($variant) {
        'primary' => 'inline-flex items-center justify-center rounded-full bg-accent px-8 py-3 text-base font-semibold text-on-accent hover:bg-accent-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:bg-line disabled:text-ink-faint',
        'secondary' => 'inline-flex items-center justify-center rounded-full border border-line-strong bg-surface px-8 py-3 text-base font-medium text-ink hover:border-accent hover:text-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:border-line disabled:text-ink-faint',
    };
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>{{ $slot }}</button>
