{{--
    The inbox's filter and status chips — docs/messaging.md § "Inbox filters
    and the seller's queue". Plain links carrying `aria-current`, the same
    idiom the logs viewer's domain/level chips already use, so a filtered
    inbox is a URL an operator can keep or share. Admin-exclusive.
--}}
@props(['filter', 'status'])

@php
    $filters = [
        'needs-reply' => 'Needs reply',
        'all' => 'All',
        'sellers' => 'Sellers',
        'customers' => 'Customers',
        'orders' => 'Orders',
        'questions' => 'Questions',
    ];
    $statuses = ['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All'];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-1.5']) }}>
    <div role="group" aria-label="Filter" class="flex flex-wrap gap-1.5">
        @foreach ($filters as $value => $label)
            <a
                href="{{ route('admin.messages.index', ['filter' => $value, 'status' => $status]) }}"
                @if ($filter === $value) aria-current="true" @endif
                class="rounded-full px-2.5 py-1 text-xs font-medium {{ $filter === $value ? 'bg-stone-900 text-white dark:bg-stone-100 dark:text-stone-900' : 'bg-stone-100 text-stone-600 hover:bg-stone-200 dark:bg-white/5 dark:text-stone-400 dark:hover:bg-white/10' }}"
            >{{ $label }}</a>
        @endforeach
    </div>

    <span class="hidden h-4 w-px bg-stone-200 dark:bg-white/10 sm:inline-block" aria-hidden="true"></span>

    <div role="group" aria-label="Status" class="flex flex-wrap gap-1.5">
        @foreach ($statuses as $value => $label)
            <a
                href="{{ route('admin.messages.index', ['filter' => $filter, 'status' => $value]) }}"
                @if ($status === $value) aria-current="true" @endif
                class="rounded-full px-2.5 py-1 text-xs font-medium {{ $status === $value ? 'bg-stone-200 text-stone-900 dark:bg-white/15 dark:text-white' : 'bg-white text-stone-500 ring-1 ring-inset ring-stone-200 hover:bg-stone-50 dark:bg-transparent dark:text-stone-500 dark:ring-white/10' }}"
            >{{ $label }}</a>
        @endforeach
    </div>
</div>
