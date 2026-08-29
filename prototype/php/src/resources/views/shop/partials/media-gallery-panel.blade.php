@php
    // The gallery-sheet picker: zero standing chrome — one pill, and the
    // picker opens as a panel of photo tiles. Under 640px the panel presents
    // as a bottom sheet (fixed to the viewport's bottom edge, grabber,
    // scrim); from `sm:` up it is an anchored overlay. Native <details>, no
    // script — the scrim lives INSIDE the <summary>, so a tap anywhere on
    // the covered page is a tap on the summary and dismisses the sheet.
    // `$browse` is MediumBrowse::forStorefront(); `$activeMedium`/`$term`
    // as elsewhere.
    $withTerm = fn (array $query): string => route('shop.home', $term !== null ? $query + ['q' => $term] : $query);
    $total = array_sum(array_column($browse, 'count'));
@endphp

@if ($browse !== [])
    <details class="group relative inline-block">
        <summary class="inline-flex cursor-pointer list-none items-center gap-2.5 rounded-full border border-line-strong bg-surface px-5 py-2.5 text-sm font-semibold text-ink hover:border-accent [&::-webkit-details-marker]:hidden">
            <span class="flex gap-0.5" aria-hidden="true">
                <span class="size-2 rounded-[3px] bg-tint-1"></span>
                <span class="size-2 rounded-[3px] bg-tint-4"></span>
                <span class="size-2 rounded-[3px] bg-tint-3"></span>
            </span>
            Browse media
            <svg width="11" height="11" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="transition-transform group-open:rotate-180"><path d="M2.5 4.5 L6 8 L9.5 4.5"></path></svg>
            <span class="fixed inset-0 z-10 hidden bg-ink/40 group-open:block sm:group-open:hidden" aria-hidden="true"></span>
        </summary>
        <div class="fixed inset-x-0 bottom-0 z-20 max-h-[75vh] overflow-y-auto rounded-t-card border border-line bg-surface p-3 shadow-xl sm:absolute sm:inset-x-auto sm:bottom-auto sm:left-0 sm:top-full sm:z-10 sm:mt-2 sm:max-h-none sm:w-[26rem] sm:max-w-[86vw] sm:overflow-visible sm:rounded-card">
            <span class="mx-auto mb-2 block h-1 w-9 rounded-full bg-line-strong sm:hidden" aria-hidden="true"></span>
            <div class="flex items-baseline justify-between px-1 pb-2">
                <span class="font-display text-base text-ink">Browse media</span>
                <span class="text-xs text-ink-faint">{{ $total }} {{ str('work')->plural($total) }}</span>
            </div>
            <a href="{{ $withTerm([]) }}" @if ($activeMedium === null) aria-current="true" @endif
               class="block rounded-field bg-ink px-3 py-2.5 text-sm font-semibold text-canvas hover:brightness-110">
                All art
            </a>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($browse as $medium)
                    @php $active = $activeMedium === $medium['value']; @endphp
                    <a href="{{ $withTerm(['medium' => $medium['value']]) }}" @if ($active) aria-current="true" @endif
                       style="background-image: url('{{ $medium['coverUrl'] }}')"
                       class="relative h-16 overflow-hidden rounded-field bg-cover bg-center hover:brightness-105 {{ $active ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
                        <span class="absolute inset-x-0 bottom-0 flex items-baseline justify-between gap-2 px-2.5 pb-1.5 pt-5 text-on-photo"
                              style="background-image: linear-gradient(to top, rgb(26 17 12 / 0.72), rgb(26 17 12 / 0))">
                            <span class="truncate text-xs font-bold">{{ $medium['label'] }}</span>
                            <span class="text-[10px] opacity-75">{{ $medium['count'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </details>
@endif
