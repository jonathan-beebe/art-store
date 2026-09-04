{{--
    The admin portal's tool nav — shared between the desktop rail and the
    mobile drawer so the two never drift apart. Takes `$navGroups`, a list of
    ['label' => ?string, 'items' => [...]] — `label` renders a section
    heading above its items (null for the ungrouped Dashboard link at the
    top); each item is ['route', 'pattern', 'label', 'count', 'path'] (count
    chip omitted when null or zero; `pattern` drives routeIs() so a link
    stays current on a section's detail pages too, not just its index
    route).
--}}
@foreach ($navGroups as $group)
    @if ($group['label'])
        <li class="mt-4 px-2 text-xs/6 font-semibold text-stone-400">{{ $group['label'] }}</li>
    @endif
    @foreach ($group['items'] as $link)
        @php($isCurrent = request()->routeIs($link['pattern']))
        <li>
            <a
                href="{{ route($link['route']) }}"
                @if ($isCurrent) aria-current="page" @endif
                class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold {{ $isCurrent ? 'bg-stone-100 text-stone-900 dark:bg-white/5 dark:text-white' : 'text-stone-700 hover:bg-stone-50 dark:text-stone-400 dark:hover:bg-white/5 dark:hover:text-white' }}"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6 shrink-0 {{ $isCurrent ? 'text-stone-900 dark:text-white' : 'text-stone-400 group-hover:text-stone-900 dark:text-stone-500 dark:group-hover:text-white' }}">
                    <path d="{{ $link['path'] }}" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                {{ $link['label'] }}
                @if (! empty($link['count']))
                    <span class="ml-auto min-w-9 rounded-full bg-white px-2.5 py-0.5 text-center text-xs/5 font-medium whitespace-nowrap text-stone-600 ring-1 ring-stone-200 ring-inset dark:bg-stone-900 dark:text-stone-400 dark:ring-white/10">{{ $link['count'] }}</span>
                @endif
            </a>
        </li>
    @endforeach
@endforeach
