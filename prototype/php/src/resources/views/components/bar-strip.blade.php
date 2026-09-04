{{-- A daily or hourly series as one SVG bar chart, read by the admin
     analytics pages, the seller listing detail, the seller dashboard, and
     the earnings page. `preserveAspectRatio="none"` and `width="100%"` let
     the same markup fill a 26px table cell or a 112px panel without
     redrawing anything: only the container's height changes. Each bar is
     one `<rect>` with `fill="currentColor"`, so the caller's `class` sets
     the whole strip's color, and a bar carrying `hot` or `negative`
     overrides its own color to red. Bars sit edge to edge past 31 of them
     — a 1-unit gap between each would shrink every bar to a hairline.
     `labelledby` names the id of a heading that already describes this
     strip (which picture, which period), carried as `role="img"`
     `aria-labelledby`; every caller without one already sits beside its
     own count or label, so the strip itself carries `aria-hidden="true"`
     instead, leaving that neighboring text as the one thing assistive
     technology reads. `baseline` is the pixel row {@see \App\Domain\Analytics\BarStrip::baseline()}
     picked for zero: a positive bar rises from it, a `negative` one drops
     below it; omitted, every bar rises from the strip's bottom edge, as
     {@see \App\Domain\Analytics\BarStrip::bars()} scales them. --}}
@props(['bars' => [], 'height' => 26, 'labelledby' => null, 'baseline' => null])

@php
    $count = count($bars);
    $unit = 3;
    $gap = $count > 0 && $count <= 31 ? 1 : 0;
    $barWidth = $unit - $gap;
    $totalWidth = max($unit, $count * $unit);
    $zero = $baseline ?? $height;
@endphp

<svg viewBox="0 0 {{ $totalWidth }} {{ $height }}" preserveAspectRatio="none" width="100%" style="height: {{ $height }}px" @if ($labelledby !== null) role="img" aria-labelledby="{{ $labelledby }}" @else aria-hidden="true" @endif {{ $attributes->merge(['class' => 'block']) }}>
    @if ($baseline !== null)
        <line x1="0" y1="{{ $zero }}" x2="{{ $totalWidth }}" y2="{{ $zero }}" stroke="currentColor" stroke-width="0.5" opacity="0.3" />
    @endif
    @foreach ($bars as $i => $bar)
        <rect
            x="{{ $i * $unit }}"
            y="{{ $bar->negative ? $zero : $zero - $bar->height }}"
            width="{{ $barWidth }}"
            height="{{ $bar->height }}"
            rx="1"
            fill="currentColor"
            @class(['text-red-600 dark:text-red-500' => $bar->hot || $bar->negative])
        ><title>{{ $bar->tip }}</title></rect>
    @endforeach
</svg>
