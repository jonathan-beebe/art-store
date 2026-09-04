{{--
    The orders list pane's lanes: underline tabs, one per lane, each a plain
    link carrying its own `?lane=`. The two lanes that ask for work wear
    their count; Done and All are archives and wear none. The negative
    bottom margin sits the active underline on the pane header's own border.
--}}
@props(['tabs'])

<nav aria-label="Lane" class="mt-3 -mb-4 flex space-x-5 overflow-x-auto">
    @foreach ($tabs as $tab)
        <a
            href="{{ $tab->href }}"
            @if ($tab->active) aria-current="page" @endif
            class="{{ $tab->active
                ? 'border-b-2 border-indigo-500 px-1 pb-4 text-sm/5 font-medium whitespace-nowrap text-gray-900 dark:text-white'
                : 'border-b-2 border-transparent px-1 pb-4 text-sm/5 font-medium whitespace-nowrap text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-white/20 dark:hover:text-gray-200' }}"
        >{{ $tab->lane->label() }}@if ($tab->count !== null)<span class="ml-1.5 rounded-full bg-gray-100 px-1.5 py-0.5 text-xs tabular-nums text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $tab->count }}</span>@endif</a>
    @endforeach
</nav>
