{{-- A daily series as one polyline with a dot on the last day. The points
     come from `App\Domain\Seller\Sparkline`, scaled in PHP against the
     same box this viewBox names, so the component draws and decides
     nothing. `overflow: visible` keeps the end dot whole where the line
     touches an edge. --}}
@props(['sparkline', 'width' => 120, 'height' => 32])

<svg viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}" aria-hidden="true" {{ $attributes->merge(['class' => 'flex-none overflow-visible text-indigo-500 dark:text-indigo-400']) }}>
    <polyline points="{{ $sparkline->points }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
    <circle cx="{{ $sparkline->endX }}" cy="{{ $sparkline->endY }}" r="2.5" fill="currentColor" />
</svg>
