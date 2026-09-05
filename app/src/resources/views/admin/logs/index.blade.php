<x-layouts.admin title="Logs — Art Store admin" mode="content-wide">
    @if (! $storeAvailable)
        <h1 class="text-xl font-semibold">Logs</h1>
        <x-admin.nothing class="mt-4">The log store is unavailable — <code>LOG_DATABASE_FILE</code> is off or the store failed to open. Every line still goes to stdout.</x-admin.nothing>
    @else
        @php
            // Fixed-width tracks for everything but the method+path column,
            // so the header strip and every summary row share the exact
            // same template and every column starts at the same x — actor
            // is widened past the reference 112px to fit its pill plus the
            // chevron button DSGN-004 added it (Change 1), so the two never
            // wrap onto a second line at the common case.
            $rowGridCols = 'grid-cols-[16px_96px_minmax(0,1fr)_52px_76px_60px_152px_116px_28px]';
        @endphp

        {{-- Header bar: title, primary controls, More filters --}}
        <div class="rounded-t-lg border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 px-5 pt-4 pb-3">
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

                <div role="group" aria-label="Domain" class="flex flex-wrap items-center gap-1">
                    @foreach ($domainLinks as $link)
                        <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="rounded-md px-2.5 py-1 text-xs font-medium {{ $link['active'] ? 'bg-stone-900 dark:bg-stone-100 text-white dark:text-stone-900' : 'bg-stone-100 dark:bg-stone-400/10 text-stone-600 dark:text-stone-400 hover:bg-stone-200 dark:hover:bg-stone-400/20' }}">{{ $link['label'] }}</a>
                    @endforeach
                </div>

                <div role="group" aria-label="Level" class="flex gap-2">
                    @foreach ($levelChips as $chip)
                        @php
                            $idleClasses = match ($chip['level']) {
                                'error' => 'bg-red-50 dark:bg-red-400/10 text-red-700 dark:text-red-400',
                                'warn' => 'bg-amber-50 dark:bg-amber-400/10 text-amber-700 dark:text-amber-400',
                                default => 'bg-stone-100 dark:bg-stone-400/10 text-stone-600 dark:text-stone-400',
                            };
                            // Active takes the same dark-fill treatment the domain segmented
                            // control's selected segment uses, replacing the severity-tinted
                            // idle background.
                            $chipClasses = $chip['active']
                                ? 'bg-stone-900 dark:bg-stone-100 font-medium text-white dark:text-stone-900'
                                : $idleClasses;
                        @endphp
                        <a href="{{ $chip['href'] }}" data-stat="level-{{ $chip['level'] }}" @if ($chip['active']) aria-current="true" @endif class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium {{ $chipClasses }}">
                            <span>{{ $chip['label'] }}</span>
                            <span data-count class="font-semibold tabular-nums">{{ $chip['count'] }}</span>
                        </a>
                    @endforeach
                </div>

                {{-- `w-56` here, not on the component: x-admin.select is
                     `block w-full` so the six filter forms can fill their own
                     fixed-width wrappers, so this header constrains it from
                     the outside instead. `sr-only` keeps "Event" announced
                     without stacking a second row above the select — the
                     compact inline pairing DSGN-004 had before this form
                     moved onto x-admin.select. --}}
                <div class="w-56">
                    <x-admin.select id="filter-event" name="event" label="Event" label-class="sr-only">
                        <option value="">all</option>
                        @foreach ($events as $event)
                            <option value="{{ $event->value }}" @selected(($filters['event'] ?? null) === $event->value)>{{ $event->value }}</option>
                        @endforeach
                    </x-admin.select>
                </div>

                <div class="flex-1"></div>

                <div role="group" aria-label="View" class="flex flex-wrap items-center gap-1">
                    @foreach ($viewLinks as $link)
                        <a href="{{ $link['href'] }}" @if ($link['active']) aria-current="true" @endif class="rounded-md px-2.5 py-1 text-xs font-medium {{ $link['active'] ? 'bg-stone-900 dark:bg-stone-100 text-white dark:text-stone-900' : 'bg-stone-100 dark:bg-stone-400/10 text-stone-600 dark:text-stone-400 hover:bg-stone-200 dark:hover:bg-stone-400/20' }}">{{ $link['label'] }}</a>
                    @endforeach
                </div>

                <details class="relative w-full sm:w-auto">
                    {{-- `relative z-20`, above the panel's own `z-10`, is what
                         keeps this button visible and tappable once the panel
                         is open — on top of it regardless of the panel's
                         exact `top` offset below, since native `<details>`
                         gives us no other JS-free way to close the mobile
                         takeover. --}}
                    <summary class="relative z-20 inline-flex min-h-11 sm:min-h-0 cursor-pointer items-center gap-1.5 rounded-md bg-white dark:bg-white/10 px-2.5 py-1.5 text-sm/6 font-semibold text-stone-900 dark:text-white shadow-xs inset-ring inset-ring-stone-300 dark:inset-ring-white/5 hover:bg-stone-50 dark:hover:bg-white/20 dark:shadow-none">
                        <span>More filters</span>
                        @if ($moreFiltersActive)
                            <span aria-hidden="true" class="inline-block h-1.5 w-1.5 rounded-full bg-stone-900 dark:bg-stone-100"></span>
                            <span class="sr-only">(filters active)</span>
                        @endif
                    </summary>

                    {{-- A floating card on sm+: absolute, right-aligned under
                         the button, on top of the rows/chips beneath — opening
                         it never reflows the header or the list. Below sm: a
                         fixed viewport takeover instead, scrolling its own
                         content; `top-32` is a best-effort clearance for the
                         nav bar plus this header card above it — the
                         `summary`'s own stacking above (see its comment) is
                         what actually guarantees it stays visible whatever
                         that height turns out to be in practice. --}}
                    <div class="fixed inset-x-0 bottom-0 top-32 z-10 flex flex-col overflow-y-auto rounded-t-lg border-t border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4 shadow-lg sm:absolute sm:inset-x-auto sm:bottom-auto sm:top-full sm:right-0 sm:mt-2 sm:w-[28rem] sm:rounded-lg sm:border">
                        <h2 class="mb-3 text-sm font-semibold text-stone-900 dark:text-stone-100">Filters</h2>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <x-admin.select id="filter-phase" name="phase" label="Phase">
                                <option value="">All</option>
                                @foreach ($phases as $phase)
                                    <option value="{{ $phase->value }}" @selected(($filters['phase'] ?? null) === $phase->value)>{{ $phase->value }}</option>
                                @endforeach
                            </x-admin.select>
                            <x-admin.input id="filter-request" name="request" label="Request id" :value="$filters['request'] ?? ''" />
                            <x-admin.input id="filter-txn" name="txn" label="Transaction id" :value="$filters['txn'] ?? ''" />
                            <x-admin.input id="filter-session" name="session" label="Session id" :value="$filters['session'] ?? ''" />
                            <x-admin.input id="filter-actor" name="actor" label="Actor id" :value="$filters['actor'] ?? ''" />
                            <x-admin.input id="filter-msg" name="msg" label="Message contains" :value="$filters['msg'] ?? ''" />
                            <x-admin.input id="filter-from" name="from" label="From (UTC instant)" placeholder="2026-08-24T00:00:00Z" :value="$filters['from'] ?? ''" />
                            <x-admin.input id="filter-to" name="to" label="To (UTC instant)" placeholder="2026-08-25T00:00:00Z" :value="$filters['to'] ?? ''" />
                            <x-admin.input id="filter-key" name="key" label="Attribute key" placeholder="data.order_id" :value="$filters['key'] ?? ''" />
                            <x-admin.input id="filter-value" name="value" label="Attribute value" :value="$filters['value'] ?? ''" />
                            <div class="flex items-end gap-1.5 pb-2">
                                <input id="filter-health" name="health" type="checkbox" value="1" @checked(($filters['health'] ?? null) === '1')>
                                <label for="filter-health" class="text-stone-700 dark:text-stone-300">Include health checks</label>
                            </div>
                            <div class="flex items-end gap-1.5 pb-2">
                                <input id="filter-viewer" name="viewer" type="checkbox" value="1" @checked(($filters['viewer'] ?? null) === '1')>
                                <label for="filter-viewer" class="text-stone-700 dark:text-stone-300">Include log viewer requests</label>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center gap-3 border-t border-stone-200 dark:border-stone-800 pt-3">
                            <x-admin.button-primary>Apply filters</x-admin.button-primary>
                            <x-admin.clear-link :href="route('admin.logs.index')" />
                        </div>
                    </div>
                </details>

                <x-admin.button-primary>Filter</x-admin.button-primary>
                <x-admin.clear-link :href="route('admin.logs.index')" />
            </form>
        </div>

        {{-- Applied-state strip --}}
        <div class="flex flex-wrap items-center gap-2 border-x border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/30 px-5 py-2 text-xs text-stone-600 dark:text-stone-400">
            @foreach ($activeFilterChips as $chip)
                <span class="inline-flex items-center gap-x-0.5 rounded-md bg-stone-100 dark:bg-stone-400/10 px-2 py-1 text-stone-600 dark:text-stone-400 inset-ring inset-ring-stone-500/10 dark:inset-ring-stone-400/20">
                    {{ $chip['text'] }}
                    <a href="{{ $chip['href'] }}" aria-label="Clear {{ $chip['label'] }} filter" class="group relative -mr-1 ml-0.5 size-3.5 rounded-xs hover:bg-stone-500/20">
                        <span class="sr-only">Clear</span>
                        <svg viewBox="0 0 14 14" class="size-3.5 stroke-stone-600/50 group-hover:stroke-stone-600/75 dark:stroke-stone-400"><path d="M4 4l6 6m0-6l-6 6" stroke-linecap="round" stroke-width="1.5" /></svg>
                        <span class="absolute -inset-1"></span>
                    </a>
                </span>
            @endforeach
            <span>
                @if ($healthToggle['hidden'])
                    Health checks hidden &middot; <a href="{{ $healthToggle['href'] }}" class="underline">show</a>
                @else
                    Health checks shown &middot; <a href="{{ $healthToggle['href'] }}" class="underline">hide</a>
                @endif
            </span>
            <span>
                @if ($viewerToggle['hidden'])
                    Log viewer traffic hidden &middot; <a href="{{ $viewerToggle['href'] }}" class="underline">show</a>
                @else
                    Log viewer traffic shown &middot; <a href="{{ $viewerToggle['href'] }}" class="underline">hide</a>
                @endif
            </span>
            <span>
                @if ($mcpToggle['hidden'])
                    MCP traffic hidden &middot; <a href="{{ $mcpToggle['href'] }}" class="underline">show</a>
                @else
                    MCP traffic shown &middot; <a href="{{ $mcpToggle['href'] }}" class="underline">hide</a>
                @endif
            </span>
            <span class="flex-1"></span>
            <span>{{ number_format($page->totalCount) }} {{ $grouped ? 'request' : 'line' }}{{ $page->totalCount === 1 ? '' : 's' }} match</span>
        </div>

        @if ($grouped)
            @if (count($groups) === 0)
                <x-admin.nothing class="mt-4">No log lines match these filters.</x-admin.nothing>
            @else
                <div class="shrink-0 overflow-hidden rounded-b-lg border-x border-b border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
                    <div aria-hidden="true" class="hidden sm:grid {{ $rowGridCols }} gap-3.5 border-b border-stone-200 dark:border-stone-800 bg-stone-50 dark:bg-stone-800/50 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400">
                        <span></span>
                        <span>Time</span>
                        <span>Request</span>
                        <span>Status</span>
                        <span class="text-right">Duration</span>
                        <span>Lines</span>
                        <span>Actor</span>
                        <span>Session</span>
                        <span></span>
                    </div>

                    @foreach ($groups as $group)
                        @php
                            $severity = \App\Logging\Admin\LogSeverity::worstOf($group->lines);
                            $summary = \App\Logging\Admin\LogStoryHeader::of($group->lines);
                            $tint = \App\Logging\Admin\LogDurationTint::ofMs($group->durationMs);
                            // The row's second line, below `sm`: the method+path a
                            // request carries, or the message a non-request group
                            // (a background job, an event with no story route) was
                            // grouped under — the same fallback `method-path`
                            // already uses at `sm` and up.
                            $mobileHeadline = $group->kind === 'request' && ($group->method !== null || $group->path !== null)
                                ? trim($group->method.' '.$group->path)
                                : ($group->msg ?? '—');
                        @endphp
                        <details data-group="{{ $group->key }}" data-severity="{{ strtolower($severity->name) }}" class="group border-b border-stone-200 dark:border-stone-800 last:border-b-0 {{ $severity->rowClasses() }}">
                            <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                                {{-- `sm` and up: today's grid row, unchanged — a tap
                                     toggles the panel below. --}}
                                <div class="hidden sm:grid {{ $rowGridCols }} min-h-11 items-center gap-3.5 px-4 py-2">
                                    {{-- The open/closed affordance: rotates when the row is open.
                                         Decorative — <summary> itself announces the state. --}}
                                    <span aria-hidden="true" class="flex items-center text-stone-400 dark:text-stone-600">
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="transition-transform group-open:rotate-90"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                    </span>
                                    <span data-cell="ts" title="{{ $group->lastTs }}" class="font-mono text-xs tabular-nums text-stone-500 dark:text-stone-400">{{ \App\Logging\Admin\LogTimestamp::timeOfDay($group->lastTs) }}</span>
                                    <span data-cell="method-path" class="truncate font-mono text-xs font-semibold">
                                        @if ($group->kind === 'request' && ($group->method !== null || $group->path !== null))
                                            {{ $group->method }} {{ $group->path }}
                                        @else
                                            {{ $group->msg ?? '—' }}
                                        @endif
                                    </span>
                                    <span data-cell="status" class="justify-self-start">
                                        @if ($group->status === null)
                                            <span class="text-stone-300 dark:text-stone-700">—</span>
                                        @else
                                            <span class="inline-flex rounded-md px-1.5 py-0.5 font-mono text-xs {{ $group->status >= 400 ? 'bg-red-100 dark:bg-red-900/40 font-semibold text-red-800 dark:text-red-300' : 'bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300' }}">{{ $group->status }}</span>
                                        @endif
                                    </span>
                                    <span data-cell="duration" class="text-right font-mono text-xs tabular-nums {{ $tint?->textClasses() ?? 'text-stone-400 dark:text-stone-600' }}">{{ $group->durationMs === null ? '—' : $group->durationMs.' ms' }}</span>
                                    <span data-cell="line-count" class="text-stone-500 dark:text-stone-400">{{ $group->lineCount }}</span>
                                    <span data-cell="actor" class="min-w-0">
                                        <x-admin.log-actor :actor-type="$summary->actorType" :actor-id="$summary->actorId" :filters="$filters" :truncate="true" />
                                    </span>
                                    <span data-cell="session" class="min-w-0">
                                        @if ($summary->sessionId === null)
                                            <span class="text-stone-300 dark:text-stone-700">—</span>
                                        @else
                                            <x-admin.log-id-chip :id="$summary->sessionId" :href="\App\Logging\Admin\LogFilterLinks::href('session', $summary->sessionId, $filters)" />
                                        @endif
                                    </span>
                                    <span>
                                        @if ($group->kind === 'request')
                                            <a href="{{ route('admin.logs.story', ['requestId' => $group->key]) }}" aria-label="Open request story for {{ $group->key }}" class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-white dark:bg-white/10 text-stone-600 dark:text-stone-400 shadow-xs inset-ring inset-ring-stone-300 dark:inset-ring-white/5 hover:bg-stone-50 dark:hover:bg-white/20">
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </a>
                                        @endif
                                    </span>
                                </div>

                                {{-- Below `sm`: a two-line card. A request group's
                                     card is a real link straight to its story — a
                                     nested <a> inside <summary> follows its href
                                     rather than toggling the panel, the same way
                                     the "Open request story" chevron above already
                                     does. A group with no story route (nothing to
                                     navigate to) stays a plain row, so the tap
                                     falls through to the native toggle instead. --}}
                                @if ($group->kind === 'request')
                                    {{-- No leading caret here: this card navigates to
                                         the story rather than expanding, so the
                                         open/closed affordance would promise
                                         something the tap never does. The trailing
                                         chevron carries the meaning instead. --}}
                                    <a href="{{ route('admin.logs.story', ['requestId' => $group->key]) }}" aria-label="Open request story for {{ $group->key }}" class="flex min-h-11 flex-col gap-1 px-4 py-2 sm:hidden">
                                        <div class="flex items-center gap-2">
                                            <span title="{{ $group->lastTs }}" class="font-mono text-xs tabular-nums text-stone-500 dark:text-stone-400">{{ \App\Logging\Admin\LogTimestamp::timeOfDay($group->lastTs) }}</span>
                                            @if ($group->status === null)
                                                <span class="text-stone-300 dark:text-stone-700">—</span>
                                            @else
                                                <span class="inline-flex rounded-md px-1.5 py-0.5 font-mono text-xs {{ $group->status >= 400 ? 'bg-red-100 dark:bg-red-900/40 font-semibold text-red-800 dark:text-red-300' : 'bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300' }}">{{ $group->status }}</span>
                                            @endif
                                            <span class="flex-1"></span>
                                            <span class="font-mono text-xs tabular-nums {{ $tint?->textClasses() ?? 'text-stone-400 dark:text-stone-600' }}">{{ $group->durationMs === null ? '—' : $group->durationMs.' ms' }}</span>
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" class="flex-shrink-0 text-stone-400 dark:text-stone-600"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                        </div>
                                        <div class="truncate font-mono text-xs font-semibold">{{ $mobileHeadline }}</div>
                                    </a>
                                @else
                                    <div class="flex min-h-11 flex-col gap-1 px-4 py-2 sm:hidden">
                                        <div class="flex items-center gap-2">
                                            <span aria-hidden="true" class="flex flex-shrink-0 items-center text-stone-400 dark:text-stone-600">
                                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" class="transition-transform group-open:rotate-90"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </span>
                                            <span title="{{ $group->lastTs }}" class="font-mono text-xs tabular-nums text-stone-500 dark:text-stone-400">{{ \App\Logging\Admin\LogTimestamp::timeOfDay($group->lastTs) }}</span>
                                            @if ($group->status === null)
                                                <span class="text-stone-300 dark:text-stone-700">—</span>
                                            @else
                                                <span class="inline-flex rounded-md px-1.5 py-0.5 font-mono text-xs {{ $group->status >= 400 ? 'bg-red-100 dark:bg-red-900/40 font-semibold text-red-800 dark:text-red-300' : 'bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300' }}">{{ $group->status }}</span>
                                            @endif
                                            <span class="flex-1"></span>
                                            <span class="font-mono text-xs tabular-nums {{ $tint?->textClasses() ?? 'text-stone-400 dark:text-stone-600' }}">{{ $group->durationMs === null ? '—' : $group->durationMs.' ms' }}</span>
                                        </div>
                                        <div class="truncate pl-[22px] font-mono text-xs font-semibold">{{ $mobileHeadline }}</div>
                                    </div>
                                @endif
                            </summary>

                            <div class="border-t border-stone-200 dark:border-stone-800 bg-stone-50/60 dark:bg-stone-950/20">
                                @if ($group->kind === 'request')
                                    <x-admin.log-filter-rail
                                        class="border-b border-stone-200 dark:border-stone-800 px-4 py-2.5"
                                        :request-id="$group->key"
                                        :txn-id="$summary->txnId"
                                        :session-id="$summary->sessionId"
                                        :actor-type="$summary->actorType"
                                        :actor-id="$summary->actorId"
                                        :filters="$filters"
                                    />
                                @endif
                                <x-admin.log-lines :lines="$group->lines" :open="false" :filters="$filters" />
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        @elseif (count($lines) === 0)
            <x-admin.nothing class="mt-4">No log lines match these filters.</x-admin.nothing>
        @else
            <div class="overflow-x-auto rounded-b-lg border-x border-b border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Log lines, newest first</caption>
                    <thead class="border-b border-stone-200 dark:border-stone-800 bg-stone-50 dark:bg-stone-800/50 text-[11px] font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400">
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
                    <tbody class="divide-y divide-stone-100 dark:divide-stone-800">
                        @foreach ($lines as $line)
                            @php
                                $severity = \App\Logging\Admin\LogSeverity::ofLevel($line->level);
                                $tint = \App\Logging\Admin\LogDurationTint::ofMs($line->durationMs);
                            @endphp
                            <tr data-line="{{ $line->id }}" data-severity="{{ strtolower($severity->name) }}" class="{{ $severity->rowClasses() }}">
                                <td data-cell="ts" title="{{ $line->ts }}" class="px-4 py-2.5 align-top whitespace-nowrap font-mono text-xs tabular-nums text-stone-500 dark:text-stone-400">{{ \App\Logging\Admin\LogTimestamp::timeOfDay($line->ts) }}</td>
                                <td data-cell="level" class="px-4 py-2.5 align-top"><span class="inline-flex rounded-md bg-stone-100 dark:bg-stone-800 px-1.5 py-0.5 text-[11px] font-semibold text-stone-600 dark:text-stone-300">{{ $line->level ?? '—' }}</span></td>
                                <td data-cell="event" class="px-4 py-2.5 align-top whitespace-nowrap font-mono text-xs text-stone-600 dark:text-stone-400">{{ $line->event ?? '—' }}@if ($line->phase !== null) &middot; {{ $line->phase }}@endif</td>
                                <td data-cell="msg" class="px-4 py-2.5 align-top">
                                    {{ $line->msg ?? '—' }}
                                    @if ($line->data !== null)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-xs text-stone-500 dark:text-stone-400">data</summary>
                                            <pre class="mt-1 overflow-x-auto rounded-md bg-stone-50 dark:bg-stone-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->data)) !!}</pre>
                                        </details>
                                    @endif
                                    @if ($line->error !== null)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-xs text-stone-500 dark:text-stone-400">error</summary>
                                            <pre class="mt-1 overflow-x-auto rounded-md bg-stone-50 dark:bg-stone-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->error)) !!}</pre>
                                        </details>
                                    @endif
                                    <x-admin.log-ids :line="$line" :filters="$filters" :exclude="['request', 'actor']" />
                                </td>
                                <td data-cell="request" class="px-4 py-2.5 align-top whitespace-nowrap">
                                    @if ($line->requestId === null)
                                        <span class="text-stone-300 dark:text-stone-700">—</span>
                                    @else
                                        <x-admin.log-id-chip :id="$line->requestId" :href="\App\Logging\Admin\LogFilterLinks::href('request', $line->requestId, $filters)" />
                                        <a href="{{ route('admin.logs.story', ['requestId' => $line->requestId]) }}" aria-label="Open request story for {{ $line->requestId }}" class="ml-1 inline-flex h-6 w-6 items-center justify-center rounded-md text-stone-600 dark:text-stone-400 inset-ring inset-ring-stone-300 dark:inset-ring-stone-700 hover:bg-stone-50 dark:hover:bg-white/10">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                        </a>
                                    @endif
                                </td>
                                <td data-cell="actor" class="px-4 py-2.5 align-top whitespace-nowrap">
                                    <x-admin.log-actor :actor-type="$line->actorType" :actor-id="$line->actorId" :filters="$filters" :truncate="true" />
                                </td>
                                <td data-cell="duration" class="px-4 py-2.5 align-top text-right font-mono text-xs tabular-nums {{ $tint?->textClasses() ?? 'text-stone-400 dark:text-stone-600' }}">{{ $line->durationMs === null ? '—' : $line->durationMs.' ms' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-admin.pager :page="$page" base-url="{{ route('admin.logs.index') }}" :query="$filterQuery" />
    @endif
</x-layouts.admin>
