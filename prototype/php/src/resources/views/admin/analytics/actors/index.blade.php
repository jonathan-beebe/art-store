@php
    $segmentActive = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-900 dark:bg-stone-100 text-white dark:text-stone-900';
    $segmentIdle = 'rounded-md px-2.5 py-1 text-xs font-medium bg-stone-100 dark:bg-stone-400/10 text-stone-600 dark:text-stone-400 hover:bg-stone-200 dark:hover:bg-stone-400/20';
@endphp

<x-layouts.admin title="All actors — Art Store admin" mode="content-wide">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ $indexHref }}" class="inline-flex items-center gap-1.5 text-stone-600 dark:text-stone-400 hover:text-stone-900 dark:hover:text-stone-100">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            <span>Analytics</span>
        </a>
        <span class="text-stone-400 dark:text-stone-600">/</span>
        <h1 class="mt-1 flex flex-wrap items-center gap-3 text-lg font-semibold text-stone-900 dark:text-stone-100">All actors</h1>
        <span class="ml-auto text-stone-600 dark:text-stone-400">{{ $rangeCaption }}</span>
        <div role="group" aria-label="Range" class="flex gap-1">
            @foreach ($rangeLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>

    {{-- toolbar --}}
    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        <span class="text-stone-600 dark:text-stone-400">Sort</span>
        <div role="group" aria-label="Sort" class="flex gap-1">
            @foreach ($sortLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>

        <div class="h-5 w-px bg-stone-200 dark:bg-stone-700"></div>

        <div role="group" aria-label="Actors" class="flex gap-1">
            @foreach ($actorFilterLinks as $link)
                <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="{{ $link['active'] ? $segmentActive : $segmentIdle }}">{{ $link['label'] }}</a>
            @endforeach
        </div>

        <x-admin.analytics.search-form
            action="{{ route('admin.analytics.actors.index') }}"
            id="actors-search"
            :search="$search"
            placeholder="Search by id, email, or IP"
            :carry="$roundTripped"
            class="min-w-64"
        />

        <span class="ml-auto text-xs text-stone-500 dark:text-stone-400">{{ number_format($page->totalCount) }} actors in the range</span>
    </div>

    @if ($page->totalCount === 0)
        <x-admin.nothing class="mt-4">No actor activity in this range.</x-admin.nothing>
    @else
        <div class="mt-4 hidden overflow-x-auto rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
            <table class="w-full text-left">
                <caption class="sr-only">Every actor in the range</caption>
                <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Actor</th>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Identity</th>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">IPs</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Events</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Peak / hour</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold whitespace-nowrap">Subjects</th>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">First seen</th>
                        <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Last seen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                    @foreach ($rows as $actor)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal">
                                <a href="{{ route('admin.analytics.actors.show', $actor->id) }}" class="underline">
                                    <x-admin.log-id-chip :id="$actor->id" />
                                </a>
                            </th>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2">
                                    <x-admin.status-badge :tint="$actor->kind === 'verified' ? 'ok' : 'neutral'">{{ $actor->kind }}</x-admin.status-badge>
                                    <span class="text-stone-600 dark:text-stone-400">{{ $actor->who }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2 font-mono text-xs text-stone-600 dark:text-stone-400">{{ implode(', ', $actor->ips) }}</td>
                            <td class="px-4 py-2 text-right font-semibold tabular-nums text-stone-900 dark:text-stone-100">{{ number_format($actor->events) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums {{ $actor->flagged ? 'font-bold text-red-700 dark:text-red-500' : 'text-stone-900 dark:text-stone-100' }}">{{ number_format($actor->peakPerHour) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format($actor->subjects) }}</td>
                            <td class="px-4 py-2 font-mono text-[11px] whitespace-nowrap text-stone-500 dark:text-stone-400">{{ $actor->firstSeenAt->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 font-mono text-[11px] whitespace-nowrap text-stone-500 dark:text-stone-400">{{ \App\Support\RelativeTime::short($actor->lastSeenAt, $now) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-admin.card-list caption="Every actor in the range">
            @foreach ($rows as $actor)
                <x-admin.card-row href="{{ route('admin.analytics.actors.show', $actor->id) }}">
                    <div class="flex items-center justify-between gap-3">
                        <x-admin.log-id-chip :id="$actor->id" />
                        <span class="{{ $actor->flagged ? 'font-bold text-red-700 dark:text-red-500' : 'text-stone-900 dark:text-white' }}">{{ number_format($actor->peakPerHour) }}/h</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-admin.status-badge :tint="$actor->kind === 'verified' ? 'ok' : 'neutral'">{{ $actor->kind }}</x-admin.status-badge>
                        <span class="text-stone-600 dark:text-stone-400">{{ $actor->who }}</span>
                    </div>
                    <div class="flex items-center justify-between text-stone-600 dark:text-stone-400">
                        <span>{{ number_format($actor->events) }} events · {{ number_format($actor->subjects) }} subjects</span>
                        <span>{{ \App\Support\RelativeTime::short($actor->lastSeenAt, $now) }}</span>
                    </div>
                </x-admin.card-row>
            @endforeach
        </x-admin.card-list>

        <x-admin.pager :page="$page" base-url="{{ route('admin.analytics.actors.index') }}" :query="$filterQuery" />
    @endif
</x-layouts.admin>
