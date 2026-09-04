{{-- A daily series as one SVG bar chart (mirrors x-admin.analytics.bar-strip
     for the seller portal's indigo accent). `preserveAspectRatio="none"` and
     `width="100%"` let the same markup fill any container height; only the
     `height` prop changes. Each bar is one `<rect>` with `fill="currentColor"`,
     so the caller's `class` sets the whole strip's color. --}}
@props(['bars' => [], 'height' => 72])

@php
    $count = count($bars);
    $unit = 3;
    $gap = $count > 0 && $count <= 31 ? 1 : 0;
    $barWidth = $unit - $gap;
    $totalWidth = max($unit, $count * $unit);
@endphp

<svg viewBox="0 0 {{ $totalWidth }} {{ $height }}" preserveAspectRatio="none" width="100%" style="height: {{ $height }}px" {{ $attributes->merge(['class' => 'block']) }}>
    @foreach ($bars as $i => $bar)
        <rect
            x="{{ $i * $unit }}"
            y="{{ $height - $bar->height }}"
            width="{{ $barWidth }}"
            height="{{ $bar->height }}"
            rx="1"
            fill="currentColor"
        ><title>{{ $bar->tip }}</title></rect>
    @endforeach
</svg>
