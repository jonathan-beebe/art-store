@props(['active' => false, 'href' => null])

@php
    $classes = 'inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 text-sm font-medium transition-colors '.($active
        ? 'bg-ink text-canvas'
        : 'border border-line bg-surface text-ink-muted hover:border-line-strong hover:text-ink');
@endphp

@if ($href !== null)
    <a href="{{ $href }}" @if ($active) aria-current="true" @endif {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
@endif
