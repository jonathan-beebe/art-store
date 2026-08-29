@php
    // The browse row: one tinted tile per Medium plus "All art", cycling
    // the theme's five tints so a medium keeps its color as the set grows.
    // `$media` is MediumOptions::forStorefront(); `$activeMedium` the
    // selected value or null; `$term` the search term to preserve, or null.
    $tints = ['bg-tint-1', 'bg-tint-2', 'bg-tint-3', 'bg-tint-4', 'bg-tint-5'];
    $tileClasses = 'flex h-16 items-end rounded-card p-3 text-sm font-semibold';
    $withTerm = fn (array $query): string => route('shop.home', $term !== null ? $query + ['q' => $term] : $query);
@endphp

@if ($media !== [])
    <nav aria-label="Browse by medium" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <a href="{{ $withTerm([]) }}" @if ($activeMedium === null) aria-current="true" @endif
           class="{{ $tileClasses }} border border-line-strong bg-surface text-ink hover:border-accent {{ $activeMedium === null ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
            All art
        </a>
        @foreach ($media as $index => $option)
            <a href="{{ $withTerm(['medium' => $option['value']]) }}" @if ($activeMedium === $option['value']) aria-current="true" @endif
               class="{{ $tileClasses }} text-on-tint {{ $tints[$index % 5] }} hover:brightness-105 {{ $activeMedium === $option['value'] ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
                {{ $option['label'] }}
            </a>
        @endforeach
    </nav>
@endif
