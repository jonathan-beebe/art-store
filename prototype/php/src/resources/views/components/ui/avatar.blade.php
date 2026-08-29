{{--
    A maker's mark: their initial on a tint picked deterministically from
    their name, so the same maker is the same color everywhere. Decorative
    by default — the caller renders the name beside it.
--}}
@props(['name', 'size' => 'md'])

@php
    $tints = ['bg-tint-1', 'bg-tint-2', 'bg-tint-3', 'bg-tint-4', 'bg-tint-5'];
    $sizes = [
        'xs' => 'size-6 text-[11px]',
        'sm' => 'size-8 text-xs',
        'md' => 'size-10 text-sm',
        'lg' => 'size-14 text-lg',
    ];
    $trimmed = trim($name);
    $initial = $trimmed === '' ? '?' : mb_strtoupper(mb_substr($trimmed, 0, 1));
@endphp

<span aria-hidden="true" {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-on-tint '.$tints[crc32($trimmed) % 5].' '.$sizes[$size]]) }}>{{ $initial }}</span>
