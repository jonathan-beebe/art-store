{{-- A daily or hourly series as one SVG bar chart, read by the admin
     analytics pages, the seller listing detail, and the seller dashboard. `preserveAspectRatio="none"`
     and `width="100%"` let the same markup fill a 26px table cell or a
     112px panel without redrawing anything: only the container's height
     changes. Each bar is one `<rect>` with `fill="currentColor"`, so the
     caller's `class` sets the whole strip's color, and a bar carrying
     `hot` overrides its own color to red. Bars sit edge to edge past 31
     of them — a 1-unit gap between each would shrink every bar to a
     hairline. `labelledby` names the id of a heading that already
     describes this strip (which picture, which period), carried as
     `role="img"` `aria-labelledby` rather than a repeated `aria-label`
     string; omitted, the strip carries neither, since a caller with no
     such heading has nothing distinct to point at. --}}
@props(['bars' => [], 'height' => 26, 'labelledby' => null])

@php
    $count = count($bars);
    $unit = 3;
    $gap = $count > 0 && $count <= 31 ? 1 : 0;
    $barWidth = $unit - $gap;
    $totalWidth = max($unit, $count * $unit);
@endphp

<svg viewBox="0 0 {{ $totalWidth }} {{ $height }}" preserveAspectRatio="none" width="100%" style="height: {{ $height }}px" @if ($labelledby !== null) role="img" aria-labelledby="{{ $labelledby }}" @endif {{ $attributes->merge(['class' => 'block']) }}>
    @foreach ($bars as $i => $bar)
        <rect
            x="{{ $i * $unit }}"
            y="{{ $height - $bar->height }}"
            width="{{ $barWidth }}"
            height="{{ $bar->height }}"
            rx="1"
            fill="currentColor"
            @class(['text-red-600 dark:text-red-500' => $bar->hot])
        ><title>{{ $bar->tip }}</title></rect>
    @endforeach
</svg>
