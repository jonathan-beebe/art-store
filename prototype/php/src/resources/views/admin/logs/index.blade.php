<x-layouts.admin title="Logs — Art Store admin" :full-width="true">
    @if (! $storeAvailable)
        <h1 class="text-xl font-semibold">Logs</h1>
        <x-admin.nothing class="mt-4">The log store is unavailable — <code>LOG_DATABASE_FILE</code> is off or the store failed to open. Every line still goes to stdout.</x-admin.nothing>
    @else
        @php
            $rowGridCols = 'grid-cols-[92px_minmax(0,1fr)_56px_76px_56px_112px_112px_32px]';
        @endphp

        {{-- Header bar: title, primary controls, More filters --}}
        <div class="rounded-t border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 pt-4 pb-3">
            <h1 class="text-lg font-semibold">Logs</h1>

            <form method="GET" action="{{ route('admin.logs.index') }}" class="mt-3 flex flex-wrap items-center gap-3">
                {{-- The domain/level/view segmented controls and chips below
                     navigate directly (they carry every current filter in
                     their own href already); these hidden fields are what
                     keeps the event/phase selects and More-filters submit
                     from silently dropping the active domain/level/group. --}}
                <input type="hidden" name="domain" value="{{ $filters['domain'] ?? '' }}">
                <input type="hidden" name="level" value="{{ $filters['level'] ?? '' }}">
                <input type="hidden" name="group" value="{{ $filters['group'] ?? '' }}">

                <div role="group" aria-label="Domain" class="inline-flex overflow-hidden rounded border border-gray-300 dark:border-gray-700">
                    @foreach ($domainLinks as $link)
                        <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="border-r border-gray-300 dark:border-gray-700 px-3 py-1.5 text-sm last:border-r-0 {{ $link['active'] ? 'bg-gray-900 dark:bg-gray-100 font-medium text-white dark:text-gray-900' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">{{ $link['label'] }}</a>
                    @endforeach
                </div>

                <div role="group" aria-label="Level" class="flex gap-2">
                    @foreach ($levelChips as $chip)
                        @php
                            $chipClasses = match ($chip['level']) {
                                'error' => 'border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-400',
                                'warn' => 'border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400',
                                default => 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400',
                            };
                        @endphp
                        <a href="{{ $chip['href'] }}" data-stat="level-{{ $chip['level'] }}" @if ($chip['active']) aria-current="true" @endif class="inline-flex items-center gap-1.5 rounded border px-3 py-1.5 text-sm {{ $chipClasses }} {{ $chip['active'] ? 'ring-2 ring-gray-900 dark:ring-gray-100' : '' }}">
                            <span>{{ $chip['label'] }}</span>
                            <span data-count class="font-semibold tabular-nums">{{ $chip['count'] }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <label for="filter-event" class="text-gray-600 dark:text-gray-400">Event</label>
                    <select id="filter-event" name="event" class="rounded border border-gray-400 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 py-1.5">
                        <option value="">all</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->value }}" @selected(($filters['event'] ?? null) === $event->value)>{{ $event->value }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1"></div>

                <div role="group" aria-label="View" class="inline-flex overflow-hidden rounded border border-gray-300 dark:border-gray-700">
                    @foreach ($viewLinks as $link)
                        <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="border-r border-gray-300 dark:border-gray-700 px-3 py-1.5 text-sm last:border-r-0 {{ $link['active'] ? 'bg-gray-900 dark:bg-gray-100 font-medium text-white dark:text-gray-900' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">{{ $link['label'] }}</a>
                    @endforeach
                </div>

                <details class="w-full sm:w-auto">
                    <summary class="inline-flex cursor-pointer items-center gap-1.5 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400">
                        <span>More filters</span>
                        @if ($moreFiltersActive)
                            <span aria-hidden="true" class="inline-block h-1.5 w-1.5 rounded-full bg-gray-900 dark:bg-gray-100"></span>
                            <span class="sr-only">(filters active)</span>
                        @endif
                    </summary>

                    <div class="mt-3 grid grid-cols-1 gap-3 rounded border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="filter-phase" class="block font-medium text-gray-700 dark:text-gray-300">Phase</label>
                            <select id="filter-phase" name="phase" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                                <option value="">All</option>
                                @foreach ($phases as $phase)
                                    <option value="{{ $phase->value }}" @selected(($filters['phase'] ?? null) === $phase->value)>{{ $phase->value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="filter-request" class="block font-medium text-gray-700 dark:text-gray-300">Request id</label>
                            <input id="filter-request" name="request" type="text" value="{{ $filters['request'] ?? '' }}" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>
                        <div>
                            <label for="filter-txn" class="block font-medium text-gray-700 dark:text-gray-300">Transaction id</label>
                            <input id="filter-txn" name="txn" type="text" value="{{ $filters['txn'] ?? '' }}" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>
                        <div>
                            <label for="filter-session" class="block font-medium text-gray-700 dark:text-gray-300">Session id</label>
                            <input id="filter-session" name="session" type="text" value="{{ $filters['session'] ?? '' }}" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>
                        <div>
                            <label for="filter-actor" class="block font-medium text-gray-700 dark:text-gray-300">Actor id</label>
                            <input id="filter-actor" name="actor" type="text" value="{{ $filters['actor'] ?? '' }}" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>
                        <div>
                            <label for="filter-msg" class="block font-medium text-gray-700 dark:text-gray-300">Message contains</label>
                            <input id="filter-msg" name="msg" type="text" value="{{ $filters['msg'] ?? '' }}" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>
                        <div>
                            <label for="filter-from" class="block font-medium text-gray-700 dark:text-gray-300">From (UTC instant)</label>
                            <input id="filter-from" name="from" type="text" placeholder="2026-08-24T00:00:00Z" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>
                        <div>
                            <label for="filter-to" class="block font-medium text-gray-700 dark:text-gray-300">To (UTC instant)</label>
                            <input id="filter-to" name="to" type="text" placeholder="2026-08-25T00:00:00Z" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>
                        <div>
                            <label for="filter-key" class="block font-medium text-gray-700 dark:text-gray-300">Attribute key</label>
                            <input id="filter-key" name="key" type="text" placeholder="data.order_id" value="{{ $filters['key'] ?? '' }}" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>
                        <div>
                            <label for="filter-value" class="block font-medium text-gray-700 dark:text-gray-300">Attribute value</label>
                            <input id="filter-value" name="value" type="text" value="{{ $filters['value'] ?? '' }}" class="mt-1 w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        </div>
                        <div class="flex items-end gap-1.5 pb-2">
                            <input id="filter-health" name="health" type="checkbox" value="1" @checked(($filters['health'] ?? null) === '1')>
                            <label for="filter-health" class="text-gray-700 dark:text-gray-300">Include health checks</label>
                        </div>
                    </div>
                </details>

                <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-1.5 text-sm font-medium text-white dark:text-gray-900">Filter</button>
                <a href="{{ route('admin.logs.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline">Clear</a>
            </form>
        </div>

        {{-- Applied-state strip --}}
        <div class="flex flex-wrap items-center gap-2 border-x border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 px-5 py-2 text-xs text-gray-600 dark:text-gray-400">
            @foreach ($activeFilterChips as $chip)
                <span class="inline-flex items-center gap-1.5 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-2 py-1 text-gray-700 dark:text-gray-300">
                    {{ $chip['text'] }}
                    <a href="{{ $chip['href'] }}" aria-label="Clear {{ $chip['label'] }} filter" class="text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">&times;</a>
                </span>
            @endforeach
            <span>
                @if ($healthToggle['hidden'])
                    Health checks hidden &middot; <a href="{{ $healthToggle['href'] }}" class="underline">show</a>
                @else
                    Health checks shown &middot; <a href="{{ $healthToggle['href'] }}" class="underline">hide</a>
                @endif
            </span>
            <span class="flex-1"></span>
            <span>{{ number_format($page->totalCount) }} {{ $grouped ? 'request' : 'line' }}{{ $page->totalCount === 1 ? '' : 's' }} match</span>
        </div>

        @if ($grouped)
            @if (count($groups) === 0)
                <x-admin.nothing class="mt-4">No log lines match these filters.</x-admin.nothing>
            @else
                <div role="table" aria-label="Requests, newest first" class="overflow-hidden rounded-b border-x border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                    <div role="row" class="grid {{ $rowGridCols }} gap-3.5 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <span role="columnheader">Time</span>
                        <span role="columnheader">Request</span>
                        <span role="columnheader">Status</span>
                        <span role="columnheader" class="text-right">Duration</span>
                        <span role="columnheader">Lines</span>
                        <span role="columnheader">Actor</span>
                        <span role="columnheader">Session</span>
                        <span role="columnheader"><span class="sr-only">Open</span></span>
                    </div>

                    @foreach ($groups as $group)
                        @php
                            $severity = \App\Logging\Admin\LogSeverity::worstOf($group->lines);
                            $summary = \App\Logging\Admin\LogStoryHeader::of($group->lines);
                            $tint = \App\Logging\Admin\LogDurationTint::ofMs($group->durationMs);
                            $actorHref = $summary->actorId === null ? null : \App\Logging\Admin\LogIdLinks::hrefFor($summary->actorId);
                            $sessionHref = $summary->sessionId === null ? null : \App\Logging\Admin\LogIdLinks::hrefFor($summary->sessionId);
                        @endphp
                        <details data-group="{{ $group->key }}" data-severity="{{ strtolower($severity->name) }}" class="border-b border-gray-200 dark:border-gray-800 last:border-b-0 {{ $severity->rowClasses() }}">
                            <summary role="row" class="grid {{ $rowGridCols }} min-h-11 cursor-pointer list-none items-center gap-3.5 px-4 py-2 [&::-webkit-details-marker]:hidden">
                                <span role="cell" data-cell="ts" title="{{ $group->lastTs }}" class="font-mono text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ \App\Logging\Admin\LogTimestamp::timeOfDay($group->lastTs) }}</span>
                                <span role="cell" data-cell="method-path" class="truncate font-mono text-xs font-semibold">
                                    @if ($group->kind === 'request' && ($group->method !== null || $group->path !== null))
                                        {{ $group->method }} {{ $group->path }}
                                    @else
                                        {{ $group->msg ?? '—' }}
                                    @endif
                                </span>
                                <span role="cell" data-cell="status" class="justify-self-start">
                                    @if ($group->status === null)
                                        <span class="text-gray-300 dark:text-gray-700">—</span>
                                    @else
                                        <span class="inline-flex rounded px-1.5 py-0.5 font-mono text-xs {{ $group->status >= 400 ? 'bg-red-100 dark:bg-red-900/40 font-semibold text-red-800 dark:text-red-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300' }}">{{ $group->status }}</span>
                                    @endif
                                </span>
                                <span role="cell" data-cell="duration" class="text-right font-mono text-xs tabular-nums {{ $tint?->textClasses() ?? 'text-gray-400 dark:text-gray-600' }}">{{ $group->durationMs === null ? '—' : $group->durationMs.' ms' }}</span>
                                <span role="cell" data-cell="line-count" class="text-gray-500 dark:text-gray-400">{{ $group->lineCount }}</span>
                                <span role="cell" data-cell="actor">
                                    @if ($summary->actorId === null)
                                        <span class="text-gray-300 dark:text-gray-700">—</span>
                                    @else
                                        <x-admin.log-id-chip :id="$summary->actorId" :href="$actorHref" />
                                    @endif
                                </span>
                                <span role="cell" data-cell="session">
                                    @if ($summary->sessionId === null)
                                        <span class="text-gray-300 dark:text-gray-700">—</span>
                                    @else
                                        <x-admin.log-id-chip :id="$summary->sessionId" :href="$sessionHref" />
                                    @endif
                                </span>
                                <span role="cell">
                                    @if ($group->kind === 'request')
                                        <a href="{{ route('admin.logs.story', ['requestId' => $group->key]) }}" aria-label="Open request story" class="inline-flex h-7 w-7 items-center justify-center rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:border-gray-500 dark:hover:border-gray-500">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                        </a>
                                    @endif
                                </span>
                            </summary>

                            <div class="border-t border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-950/20">
                                @if ($group->kind === 'request')
                                    <x-admin.log-filter-rail
                                        class="border-b border-gray-200 dark:border-gray-800 px-4 py-2.5"
                                        :request-id="$group->key"
                                        :txn-id="$summary->txnId"
                                        :session-id="$summary->sessionId"
                                        :actor-type="$summary->actorType"
                                        :actor-id="$summary->actorId"
                                    />
                                @endif
                                <x-admin.log-lines :lines="$group->lines" :open="false" />
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        @elseif (count($lines) === 0)
            <x-admin.nothing class="mt-4">No log lines match these filters.</x-admin.nothing>
        @else
            <div class="overflow-x-auto rounded-b border-x border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Log lines, newest first</caption>
                    <thead class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-2">Time</th>
                            <th scope="col" class="px-4 py-2">Level</th>
                            <th scope="col" class="px-4 py-2">Event</th>
                            <th scope="col" class="px-4 py-2">Message</th>
                            <th scope="col" class="px-4 py-2">Request</th>
                            <th scope="col" class="px-4 py-2">Actor</th>
                            <th scope="col" class="px-4 py-2 text-right">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($lines as $line)
                            @php
                                $severity = \App\Logging\Admin\LogSeverity::ofLevel($line->level);
                                $tint = \App\Logging\Admin\LogDurationTint::ofMs($line->durationMs);
                                $actorHref = $line->actorId === null ? null : \App\Logging\Admin\LogIdLinks::hrefFor($line->actorId);
                            @endphp
                            <tr data-line="{{ $line->id }}" data-severity="{{ strtolower($severity->name) }}" class="{{ $severity->rowClasses() }}">
                                <td data-cell="ts" title="{{ $line->ts }}" class="px-4 py-2.5 whitespace-nowrap font-mono text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ \App\Logging\Admin\LogTimestamp::timeOfDay($line->ts) }}</td>
                                <td data-cell="level" class="px-4 py-2.5"><span class="inline-flex rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-[11px] font-semibold text-gray-600 dark:text-gray-300">{{ $line->level ?? '—' }}</span></td>
                                <td data-cell="event" class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-gray-600 dark:text-gray-400">{{ $line->event ?? '—' }}@if ($line->phase !== null) &middot; {{ $line->phase }}@endif</td>
                                <td data-cell="msg" class="px-4 py-2.5">
                                    {{ $line->msg ?? '—' }}
                                    @if ($line->data !== null)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400">data</summary>
                                            <pre class="mt-1 overflow-x-auto rounded bg-gray-50 dark:bg-gray-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->data)) !!}</pre>
                                        </details>
                                    @endif
                                    @if ($line->error !== null)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-xs text-gray-500 dark:text-gray-400">error</summary>
                                            <pre class="mt-1 overflow-x-auto rounded bg-gray-50 dark:bg-gray-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->error)) !!}</pre>
                                        </details>
                                    @endif
                                </td>
                                <td data-cell="request" class="px-4 py-2.5 whitespace-nowrap">
                                    @if ($line->requestId === null)
                                        <span class="text-gray-300 dark:text-gray-700">—</span>
                                    @else
                                        <x-admin.log-id-chip :id="$line->requestId" :href="route('admin.logs.story', ['requestId' => $line->requestId])" />
                                    @endif
                                </td>
                                <td data-cell="actor" class="px-4 py-2.5 whitespace-nowrap">
                                    @if ($line->actorId === null)
                                        {{ $line->actorType ?? '—' }}
                                    @else
                                        <x-admin.log-id-chip :id="$line->actorId" :href="$actorHref" />
                                    @endif
                                </td>
                                <td data-cell="duration" class="px-4 py-2.5 text-right font-mono text-xs tabular-nums {{ $tint?->textClasses() ?? 'text-gray-400 dark:text-gray-600' }}">{{ $line->durationMs === null ? '—' : $line->durationMs.' ms' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-admin.pager :page="$page" base-url="{{ route('admin.logs.index') }}" :query="$filterQuery" />
    @endif
</x-layouts.admin>
