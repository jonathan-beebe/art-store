@php
    $segmentActive = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-900 dark:bg-stone-100 text-white dark:text-stone-900';
    $segmentIdle = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-100 dark:bg-stone-400/10 text-stone-600 dark:text-stone-400 hover:bg-stone-200 dark:hover:bg-stone-400/20';

    $visitorHref = fn (string $actorId): string => route('admin.analytics.actors.show', $actorId);
@endphp

<x-layouts.admin :title="$label.' — Art Store admin'" mode="content-wide">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ $backHref }}" class="inline-flex items-center gap-1.5 text-stone-600 dark:text-stone-400 hover:text-stone-900 dark:hover:text-stone-100">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            <span>Channels</span>
        </a>
        <span class="text-stone-400 dark:text-stone-600">/</span>
        <h1 class="mt-1 flex flex-wrap items-center gap-3 text-lg font-semibold text-stone-900 dark:text-stone-100">{{ $label }}</h1>
        <span class="ml-auto text-stone-600 dark:text-stone-400">{{ $rangeCaption }}</span>
        <div role="group" aria-label="Range" class="flex gap-1">
            @foreach ($rangeLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        <span class="text-xs text-stone-500 dark:text-stone-400">{{ number_format($page->totalCount) }} visit(s) in the range</span>
    </div>

    @if ($page->totalCount === 0)
        <x-admin.nothing class="mt-4">No visits in this range.</x-admin.nothing>
    @else
        <div class="mt-4 hidden overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
            <table class="w-full text-left">
                <caption class="sr-only">Every visit this channel produced in the range</caption>
                <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">First seen</th>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Landing path</th>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Visitor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                    @foreach ($rows as $visit)
                        <tr>
                            <td class="px-4 py-2 font-mono text-[11px] whitespace-nowrap text-stone-500 dark:text-stone-400">{{ $visit->firstSeenAt->format('Y-m-d H:i') }} UTC</td>
                            <td class="px-4 py-2 font-mono text-xs text-stone-700 dark:text-stone-300">{{ $visit->landingPath }}</td>
                            <td class="px-4 py-2">
                                @if ($visit->actorId !== null)
                                    <x-admin.log-id-chip :id="$visit->actorId" :href="$visitorHref($visit->actorId)" />
                                @else
                                    <x-admin.log-id-chip :id="$visit->sessionId" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.card-list caption="Every visit this channel produced in the range">
            @foreach ($rows as $visit)
                <x-admin.card-row>
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-mono text-[11px] text-stone-500 dark:text-stone-400">{{ $visit->firstSeenAt->format('Y-m-d H:i') }} UTC</span>
                        @if ($visit->actorId !== null)
                            <x-admin.log-id-chip :id="$visit->actorId" :href="$visitorHref($visit->actorId)" />
                        @else
                            <x-admin.log-id-chip :id="$visit->sessionId" />
                        @endif
                    </div>
                    <div class="font-mono text-xs text-stone-600 dark:text-stone-400">{{ $visit->landingPath }}</div>
                </x-admin.card-row>
            @endforeach
        </x-admin.card-list>

        <x-admin.pager :page="$page" base-url="{{ route('admin.analytics.channels.show', ['key' => $channelKey]) }}" :query="$filterQuery" />
    @endif
</x-layouts.admin>
