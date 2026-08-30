@php
    // The cover cards as a one-row swipe rail — the phone-width presentation
    // of the cover-card picker, where a drawer only makes sense for a
    // pointer. Scroll-snap keeps a card seated after each flick.
    // `$browse` is MediumBrowse::forStorefront(); `$activeMedium` as
    // elsewhere.
@endphp

@if ($browse !== [])
    <nav aria-label="Browse by medium" class="flex snap-x snap-mandatory gap-2.5 overflow-x-auto pb-2">
        <a href="{{ route('shop.home') }}" @if ($activeMedium === null) aria-current="true" @endif
           class="flex h-20 w-28 shrink-0 snap-start flex-col items-start justify-between rounded-card bg-ink p-3 text-sm font-semibold text-canvas">
            <span class="flex gap-0.5" aria-hidden="true">
                <span class="size-2 rounded-[3px] bg-tint-1"></span>
                <span class="size-2 rounded-[3px] bg-tint-4"></span>
                <span class="size-2 rounded-[3px] bg-tint-3"></span>
            </span>
            All art
        </a>
        @foreach ($browse as $medium)
            @php $active = $activeMedium === $medium['value']; @endphp
            <a href="{{ route('shop.medium', ['medium' => $medium['value']]) }}" @if ($active) aria-current="true" @endif
               style="background-image: url('{{ $medium['coverUrl'] }}')"
               class="relative h-20 w-36 shrink-0 snap-start overflow-hidden rounded-card bg-cover bg-center {{ $active ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
                <span class="absolute inset-x-0 bottom-0 flex items-baseline justify-between gap-2 px-3 pb-2 pt-6 text-on-photo"
                      style="background-image: linear-gradient(to top, rgb(26 17 12 / 0.72), rgb(26 17 12 / 0))">
                    <span class="truncate text-sm font-bold">{{ $medium['label'] }}</span>
                    <span class="text-[11px] opacity-75">{{ $medium['count'] }}</span>
                </span>
            </a>
        @endforeach
    </nav>
@endif
