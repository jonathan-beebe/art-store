@php
    // The capped tile row (design-system exploration): the five best-stocked
    // media keep their tiles in one row and an "All media" tile unfolds the
    // rest — tinted tiles in the `tint` variant, listing cover photos in the
    // `photo` variant (cover cards). The drawer is a native <details>, so it
    // opens, closes, and keyboards without script. `$browse` is
    // MediumBrowse::forStorefront(); `$activeMedium` as elsewhere.
    $variant ??= 'tint';
    $tints = ['bg-tint-1', 'bg-tint-2', 'bg-tint-3', 'bg-tint-4', 'bg-tint-5'];
    $tileHeight = $variant === 'photo' ? 'h-20' : 'h-16';
    $ranked = collect($browse)->sortByDesc('count')->values();
    $top = $ranked->take(5);
    $rest = $ranked->slice(5)->sortBy('label')->values();
@endphp

@if ($browse !== [])
    <nav aria-label="Browse by medium" class="relative">
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-6">
            @foreach ($top as $index => $medium)
                @php $active = $activeMedium === $medium['value']; @endphp
                @if ($variant === 'photo')
                    <a href="{{ route('shop.medium', ['medium' => $medium['value']]) }}" @if ($active) aria-current="true" @endif
                       style="background-image: url('{{ $medium['coverUrl'] }}')"
                       class="relative {{ $tileHeight }} overflow-hidden rounded-card bg-cover bg-center hover:brightness-105 {{ $active ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
                        <span class="absolute inset-x-0 bottom-0 flex items-baseline justify-between gap-2 px-3 pb-2 pt-6 text-on-photo"
                              style="background-image: linear-gradient(to top, rgb(26 17 12 / 0.72), rgb(26 17 12 / 0))">
                            <span class="truncate text-sm font-bold">{{ $medium['label'] }}</span>
                            <span class="text-[11px] opacity-75">{{ $medium['count'] }}</span>
                        </span>
                    </a>
                @else
                    <a href="{{ route('shop.medium', ['medium' => $medium['value']]) }}" @if ($active) aria-current="true" @endif
                       class="flex {{ $tileHeight }} items-end justify-between gap-2 rounded-card p-3 text-sm font-semibold text-on-tint {{ $tints[$index % 5] }} hover:brightness-105 {{ $active ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
                        <span class="truncate">{{ $medium['label'] }}</span>
                        <span class="text-[11px] font-semibold opacity-70">{{ $medium['count'] }}</span>
                    </a>
                @endif
            @endforeach

            <details class="group">
                <summary class="flex {{ $tileHeight }} cursor-pointer list-none items-end justify-between gap-2 rounded-card border border-line-strong bg-surface p-3 text-sm font-semibold text-ink hover:border-accent [&::-webkit-details-marker]:hidden">
                    All media
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="mb-1 shrink-0 transition-transform group-open:rotate-180"><path d="M2.5 4.5 L6 8 L9.5 4.5"></path></svg>
                </summary>
                <div class="absolute inset-x-0 top-full z-10 mt-2 rounded-card border border-line bg-surface p-3 shadow-lg">
                    <div class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                        <a href="{{ route('shop.home') }}" @if ($activeMedium === null) aria-current="true" @endif
                           class="flex h-14 items-end rounded-card bg-ink p-3 text-sm font-semibold text-canvas hover:brightness-110">
                            All art
                        </a>
                        @foreach ($rest as $index => $medium)
                            @php $active = $activeMedium === $medium['value']; @endphp
                            @if ($variant === 'photo')
                                <a href="{{ route('shop.medium', ['medium' => $medium['value']]) }}" @if ($active) aria-current="true" @endif
                                   style="background-image: url('{{ $medium['coverUrl'] }}')"
                                   class="relative h-14 overflow-hidden rounded-card bg-cover bg-center hover:brightness-105 {{ $active ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
                                    <span class="absolute inset-x-0 bottom-0 flex items-baseline justify-between gap-2 px-2.5 pb-1.5 pt-4 text-on-photo"
                                          style="background-image: linear-gradient(to top, rgb(26 17 12 / 0.72), rgb(26 17 12 / 0))">
                                        <span class="truncate text-xs font-bold">{{ $medium['label'] }}</span>
                                        <span class="text-[10px] opacity-75">{{ $medium['count'] }}</span>
                                    </span>
                                </a>
                            @else
                                <a href="{{ route('shop.medium', ['medium' => $medium['value']]) }}" @if ($active) aria-current="true" @endif
                                   class="flex h-14 items-end justify-between gap-2 rounded-card p-3 text-xs font-semibold text-on-tint {{ $tints[$index % 5] }} hover:brightness-105 {{ $active ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
                                    <span class="truncate">{{ $medium['label'] }}</span>
                                    <span class="text-[10px] font-semibold opacity-70">{{ $medium['count'] }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </details>
        </div>
    </nav>
@endif
