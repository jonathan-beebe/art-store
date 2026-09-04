{{--
    The seller portal's tool nav — shared between the desktop rail and the
    mobile drawer so the two never drift apart. Takes `$navLinks`, a list of
    ['route', 'pattern', 'label', 'count', 'path'] (count chip omitted when
    null or zero; pattern drives routeIs() so a link stays current on a
    tool's sub-pages, not just its index route). The chip carries
    `data-nav-count` so a test can name it apart from every other number a
    page renders.
--}}
@foreach ($navLinks as $link)
    @php($isCurrent = request()->routeIs($link['pattern']))
    <li>
        <a
            href="{{ route($link['route']) }}"
            @if ($isCurrent) aria-current="page" @endif
            class="group flex gap-x-3 rounded-md p-2 text-sm/6 font-semibold {{ $isCurrent ? 'bg-gray-50 text-indigo-600 dark:bg-white/5 dark:text-white' : 'text-gray-700 hover:bg-gray-50 hover:text-indigo-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white' }}"
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" class="size-6 shrink-0 {{ $isCurrent ? 'text-indigo-600 dark:text-white' : 'text-gray-400 group-hover:text-indigo-600 dark:text-gray-500 dark:group-hover:text-white' }}">
                <path d="{{ $link['path'] }}" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            {{ $link['label'] }}
            @if (! empty($link['count']))
                <span data-nav-count="{{ strtolower($link['label']) }}" class="ml-auto min-w-9 rounded-full bg-white px-2.5 py-0.5 text-center text-xs/5 font-medium whitespace-nowrap text-gray-600 ring-1 ring-gray-200 ring-inset dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">{{ $link['count'] }}</span>
            @endif
        </a>
    </li>
@endforeach
