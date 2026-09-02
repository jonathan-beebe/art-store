{{--
    The inbox's two rows of chips (docs/messaging.md § "Inbox filters and
    the seller's queue"): what kind of thread, then which status. Every chip
    is a plain link carrying both query values, so clicking one never drops
    the other; `aria-current` marks the active chip in each row rather than
    color alone (WCAG 1.4.1). The show route renders this at its defaults
    (`all`/`open`) — its list pane is the same unfiltered inbox the index
    route opens with, so the chips here always point back at the index
    route rather than tracking a filter of their own.
--}}
@props(['filter', 'status', 'counts'])

@php
    $filterChips = ['all' => 'All', 'unread' => 'Unread', 'questions' => 'Questions', 'orders' => 'Orders', 'support' => 'Support'];
    $statusChips = ['all' => 'All', 'open' => 'Open', 'resolved' => 'Resolved'];
@endphp

<div class="flex flex-wrap gap-1.5">
    @foreach ($filterChips as $value => $label)
        @php $isActive = $filter === $value; @endphp
        <a
            href="{{ route('seller.messages.index', ['filter' => $value, 'status' => $status]) }}"
            @if ($isActive) aria-current="true" @endif
            class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 {{ $isActive ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'bg-white text-gray-700 outline-1 -outline-offset-1 outline-gray-300 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-300 dark:outline-white/10 dark:hover:bg-white/10' }}"
        >
            <span>{{ $label }}</span>
            @if ($value === 'unread')
                <span class="{{ $isActive ? 'text-gray-300 dark:text-gray-600' : 'text-gray-400 dark:text-gray-500' }}">{{ $counts['unread'] }}</span>
            @elseif ($value === 'questions')
                <span class="{{ $isActive ? 'text-gray-300 dark:text-gray-600' : 'text-gray-400 dark:text-gray-500' }}">{{ $counts['questions'] }}</span>
            @endif
        </a>
    @endforeach
</div>

<div class="mt-1.5 flex flex-wrap gap-1.5">
    @foreach ($statusChips as $value => $label)
        @php $isActive = $status === $value; @endphp
        <a
            href="{{ route('seller.messages.index', ['filter' => $filter, 'status' => $value]) }}"
            @if ($isActive) aria-current="true" @endif
            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 {{ $isActive ? 'bg-gray-100 text-gray-900 outline-1 -outline-offset-1 outline-gray-300 dark:bg-white/10 dark:text-white dark:outline-white/20' : 'bg-white text-gray-700 outline-1 -outline-offset-1 outline-gray-300 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-300 dark:outline-white/10 dark:hover:bg-white/10' }}"
        >{{ $label }}</a>
    @endforeach
</div>
