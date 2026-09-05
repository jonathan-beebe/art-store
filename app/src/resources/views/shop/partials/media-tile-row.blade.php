@php
    // The capped tile row (DSGN-007): the five best-stocked media keep their
    // tiles in one row and an "All media" tile unfolds the rest — tinted
    // tiles in the `tint` variant, listing cover photos in the `photo`
    // variant (cover cards). Both the row and the drawer share ONE grid
    // (`grid-cols-3 sm:grid-cols-6`) and ONE tile (`<x-tile>`, golden ratio
    // at any width), so the revealed rows read as more of the same surface
    // rather than a second, smaller component. The drawer is the row's own
    // `<details>` grid item, `open:`-spanning every column when it opens —
    // a nested grid at the same column count and gap lands its tiles on the
    // same tracks, and because it is normal flow rather than an absolute
    // overlay, opening it pushes the rest of the page down. Native
    // `<details>`, so it opens, closes, and keyboards without script.
    // `$browse` is `MediumBrowse::forStorefront()`; `$activeMedium` as
    // elsewhere.
    $variant ??= 'tint';
    $tints = ['bg-tint-1', 'bg-tint-2', 'bg-tint-3', 'bg-tint-4', 'bg-tint-5'];
    $ranked = collect($browse)->sortByDesc('count')->values();
    $top = $ranked->take(5);
    $rest = $ranked->slice(5)->sortBy('label')->values();
@endphp

@if ($browse !== [])
    <nav aria-label="Browse by medium">
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
            @foreach ($top as $index => $medium)
                <x-tile
                    :href="route('shop.medium', ['medium' => $medium['value']])"
                    :label="$medium['label']"
                    :count="$medium['count']"
                    :cover-url="$variant === 'photo' ? $medium['coverUrl'] : null"
                    :tint="$tints[$index % 5]"
                    :active="$activeMedium === $medium['value']"
                />
            @endforeach

            <details class="group col-span-1 open:col-span-3 sm:open:col-span-6">
                {{-- Closed, this is one more tile in the row, so it keeps the
                     golden ratio. Open, the details spans every column, and a
                     ratio against that width would compute to a ~770px-tall
                     bar above the revealed tiles — so the open state drops the
                     ratio and sits as a header over its own grid. --}}
                <summary class="flex aspect-[1.618/1] cursor-pointer list-none items-end justify-between gap-2 rounded-card border border-line-strong bg-surface p-3 text-sm font-semibold text-ink hover:border-accent group-open:aspect-auto group-open:items-center [&::-webkit-details-marker]:hidden">
                    All media
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="mb-1 shrink-0 transition-transform group-open:rotate-180"><path d="M2.5 4.5 L6 8 L9.5 4.5"></path></svg>
                </summary>
                <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-6">
                    <x-tile
                        :href="route('shop.home')"
                        label="All art"
                        :active="$activeMedium === null"
                        tint="bg-ink"
                        tint-text="text-canvas"
                    />
                    @foreach ($rest as $index => $medium)
                        <x-tile
                            :href="route('shop.medium', ['medium' => $medium['value']])"
                            :label="$medium['label']"
                            :count="$medium['count']"
                            :cover-url="$variant === 'photo' ? $medium['coverUrl'] : null"
                            :tint="$tints[$index % 5]"
                            :active="$activeMedium === $medium['value']"
                        />
                    @endforeach
                </div>
            </details>
        </div>
    </nav>
@endif
