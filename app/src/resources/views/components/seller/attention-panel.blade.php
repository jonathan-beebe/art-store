{{-- One focus group of the dashboard: a header naming what is waiting and
     linking to the tool that clears it, then a row per thing, each opening
     the thing itself. A group with nothing waiting shows its sentence in
     place of the rows. Takes an `App\Domain\Seller\AttentionGroup`. --}}
@props(['group'])

@php
    $actionHref = \App\Seller\AttentionToolLink::hrefOf($group->tool);
@endphp

<div class="flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
    <div class="flex items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4 dark:border-white/10 dark:bg-white/5">
        <span aria-hidden="true" class="flex size-8 flex-none items-center justify-center rounded-md bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="size-4.5">
                <path d="{{ \App\Seller\FeedIconPath::of($group->icon) }}" />
            </svg>
        </span>

        <div class="min-w-0 flex-1">
            <p class="truncate font-semibold text-gray-900 dark:text-gray-100">{{ $group->title }}</p>
            <p class="truncate text-xs/5 text-gray-500 dark:text-gray-400">{{ $group->supporting }}</p>
        </div>

        <a href="{{ $actionHref }}" class="shrink-0 text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">{{ $group->actionLabel }}</a>
    </div>

    @if ($group->isEmpty())
        <p class="px-5 py-4 text-gray-500 dark:text-gray-400">{{ $group->emptySentence }}</p>
    @else
        <ul role="list" class="flex flex-col divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($group->rows as $row)
                <li>
                    <a href="{{ $row->href }}" class="flex w-full items-center gap-3 px-5 py-3 hover:bg-gray-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-indigo-600 dark:hover:bg-white/5">
                        <span aria-hidden="true" class="flex size-8 flex-none items-center justify-center rounded-full bg-indigo-50 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $row->initials }}</span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-900 dark:text-gray-100">{{ $row->title }}</span>
                            <span class="block truncate text-xs/5 text-gray-500 dark:text-gray-400">{{ $row->supporting }}</span>
                        </span>

                        <span @class([
                            'shrink-0 text-xs/5 tabular-nums',
                            'font-medium text-red-600 dark:text-red-400' => $row->urgent,
                            'text-gray-500 dark:text-gray-400' => ! $row->urgent,
                        ])>{{ $row->meta }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($group->hidden() > 0)
            <a href="{{ $actionHref }}" class="border-t border-gray-100 px-5 py-3 text-xs/5 text-gray-500 hover:text-gray-700 dark:border-white/5 dark:text-gray-400 dark:hover:text-gray-200">{{ $group->hidden() }} more</a>
        @endif
    @endif
</div>
