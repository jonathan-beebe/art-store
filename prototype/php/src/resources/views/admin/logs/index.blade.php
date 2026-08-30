<x-layouts.admin title="Logs — Art Store admin">
    <h1 class="text-xl font-semibold">Logs</h1>

    @if (! $storeAvailable)
        <x-admin.nothing class="mt-4">The log store is unavailable — <code>LOG_DATABASE_FILE</code> is off or the store failed to open. Every line still goes to stdout.</x-admin.nothing>
    @else
        <form method="GET" action="{{ route('admin.logs.index') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
            <div>
                <label for="filter-domain" class="block font-medium text-gray-700 dark:text-gray-300">Domain</label>
                <select id="filter-domain" name="domain" class="mt-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                    <option value="">All</option>
                    @foreach ($domains as $domain)
                        <option value="{{ $domain->value }}" @selected(($filters['domain'] ?? null) === $domain->value)>{{ $domain->value }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-level" class="block font-medium text-gray-700 dark:text-gray-300">Level</label>
                <select id="filter-level" name="level" class="mt-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                    <option value="">All</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->value }}" @selected(($filters['level'] ?? null) === $level->value)>{{ $level->value }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-phase" class="block font-medium text-gray-700 dark:text-gray-300">Phase</label>
                <select id="filter-phase" name="phase" class="mt-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                    <option value="">All</option>
                    @foreach ($phases as $phase)
                        <option value="{{ $phase->value }}" @selected(($filters['phase'] ?? null) === $phase->value)>{{ $phase->value }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-event" class="block font-medium text-gray-700 dark:text-gray-300">Event</label>
                <select id="filter-event" name="event" class="mt-1 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                    <option value="">All</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->value }}" @selected(($filters['event'] ?? null) === $event->value)>{{ $event->value }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-request" class="block font-medium text-gray-700 dark:text-gray-300">Request id</label>
                <input id="filter-request" name="request" type="text" value="{{ $filters['request'] ?? '' }}" class="mt-1 w-44 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
            <div>
                <label for="filter-txn" class="block font-medium text-gray-700 dark:text-gray-300">Transaction id</label>
                <input id="filter-txn" name="txn" type="text" value="{{ $filters['txn'] ?? '' }}" class="mt-1 w-56 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
            <div>
                <label for="filter-session" class="block font-medium text-gray-700 dark:text-gray-300">Session id</label>
                <input id="filter-session" name="session" type="text" value="{{ $filters['session'] ?? '' }}" class="mt-1 w-56 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
            <div>
                <label for="filter-actor" class="block font-medium text-gray-700 dark:text-gray-300">Actor id</label>
                <input id="filter-actor" name="actor" type="text" value="{{ $filters['actor'] ?? '' }}" class="mt-1 w-56 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
            <div>
                <label for="filter-msg" class="block font-medium text-gray-700 dark:text-gray-300">Message contains</label>
                <input id="filter-msg" name="msg" type="text" value="{{ $filters['msg'] ?? '' }}" class="mt-1 w-48 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
            <div>
                <label for="filter-from" class="block font-medium text-gray-700 dark:text-gray-300">From (UTC instant)</label>
                <input id="filter-from" name="from" type="text" placeholder="2026-08-24T00:00:00Z" value="{{ $filters['from'] ?? '' }}" class="mt-1 w-48 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
            <div>
                <label for="filter-to" class="block font-medium text-gray-700 dark:text-gray-300">To (UTC instant)</label>
                <input id="filter-to" name="to" type="text" placeholder="2026-08-25T00:00:00Z" value="{{ $filters['to'] ?? '' }}" class="mt-1 w-48 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
            <div>
                <label for="filter-key" class="block font-medium text-gray-700 dark:text-gray-300">Attribute key</label>
                <input id="filter-key" name="key" type="text" placeholder="data.order_id" value="{{ $filters['key'] ?? '' }}" class="mt-1 w-44 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
            <div>
                <label for="filter-value" class="block font-medium text-gray-700 dark:text-gray-300">Attribute value</label>
                <input id="filter-value" name="value" type="text" value="{{ $filters['value'] ?? '' }}" class="mt-1 w-44 rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
            </div>
            <div class="flex items-center gap-1.5 pb-2">
                <input id="filter-group" name="group" type="checkbox" value="1" @checked(($filters['group'] ?? null) === '1')>
                <label for="filter-group" class="text-gray-700 dark:text-gray-300">Group by request</label>
            </div>
            <div class="flex items-center gap-1.5 pb-2">
                <input id="filter-health" name="health" type="checkbox" value="1" @checked(($filters['health'] ?? null) === '1')>
                <label for="filter-health" class="text-gray-700 dark:text-gray-300">Include health checks</label>
            </div>
            <div class="flex items-center gap-1.5 pb-2">
                <input id="filter-viewer" name="viewer" type="checkbox" value="1" @checked(($filters['viewer'] ?? null) === '1')>
                <label for="filter-viewer" class="text-gray-700 dark:text-gray-300">Include log viewer requests</label>
            </div>
            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Filter</button>
            <a href="{{ route('admin.logs.index') }}" class="pb-2 text-gray-700 dark:text-gray-300 underline">Clear</a>
        </form>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($tiles as $tile)
                <a href="{{ $tile['href'] }}" data-stat="level-{{ $tile['level'] }}" class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 hover:border-gray-500 dark:hover:border-gray-500">
                    <span class="block text-gray-500">{{ $tile['label'] }}</span>
                    <span data-count class="mt-1 block text-lg font-semibold tabular-nums">{{ $tile['count'] }}</span>
                </a>
            @endforeach
        </div>

        @if ($grouped)
            @if (count($groups) === 0)
                <x-admin.nothing class="mt-4">No log lines match these filters.</x-admin.nothing>
            @else
                <div class="mt-4 space-y-3">
                    @foreach ($groups as $group)
                        @php $severity = \App\Logging\Admin\LogSeverity::worstOf($group->lines); @endphp
                        <details data-group="{{ $group->key }}" data-severity="{{ strtolower($severity->name) }}" class="rounded border border-gray-300 dark:border-gray-700 p-3 {{ $severity->rowClasses() !== '' ? $severity->rowClasses() : 'bg-white dark:bg-gray-900' }}">
                            <summary class="cursor-pointer">
                                @if ($group->kind === 'request')
                                    <a href="{{ route('admin.logs.story', ['requestId' => $group->key]) }}" aria-label="Open request story for {{ $group->key }}" class="float-right inline-flex items-center rounded border border-gray-300 dark:border-gray-700 px-1.5 leading-5 text-gray-600 dark:text-gray-400 hover:border-gray-500 dark:hover:border-gray-500">&rsaquo;</a>
                                @endif
                                <span data-cell="ts" class="tabular-nums text-gray-500">{{ $group->lastTs }}</span>
                                @if ($group->kind === 'request')
                                    <a data-cell="request" href="{{ \App\Logging\Admin\LogFilterLinks::href('request', $group->key, $filters) }}" class="ml-2 underline">{{ $group->key }}</a>
                                @endif
                                <span data-cell="level" class="ml-2 rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs">{{ $group->level ?? '—' }}</span>
                                <span data-cell="msg" class="ml-2 font-medium">{{ $group->msg ?? '—' }}</span>
                                @if ($group->method !== null || $group->path !== null)
                                    <span data-cell="method-path" class="ml-2 text-gray-600 dark:text-gray-400">{{ $group->method }} {{ $group->path }}</span>
                                @endif
                                @if ($group->status !== null)
                                    <span data-cell="status" class="ml-2 text-gray-600 dark:text-gray-400">{{ $group->status }}</span>
                                @endif
                                @if ($group->durationMs !== null)
                                    <span data-cell="duration" class="ml-2 tabular-nums text-gray-500">{{ $group->durationMs }} ms</span>
                                @endif
                                <span data-cell="line-count" class="ml-2 text-gray-500">{{ $group->lineCount }} line{{ $group->lineCount === 1 ? '' : 's' }}</span>
                            </summary>
                            <x-admin.log-lines :lines="$group->lines" :open="false" :filters="$filters" />
                        </details>
                    @endforeach
                </div>
                <x-admin.pager :page="$page" base-url="{{ route('admin.logs.index') }}" :query="$filterQuery" />
            @endif
        @elseif (count($lines) === 0)
            <x-admin.nothing class="mt-4">No log lines match these filters.</x-admin.nothing>
        @else
            <div class="mt-4 overflow-x-auto rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Log lines, newest first</caption>
                    <thead class="border-b border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">At</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Level</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Event</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Message</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Request</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Actor</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">ms</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($lines as $line)
                            @php $severity = \App\Logging\Admin\LogSeverity::ofLevel($line->level); @endphp
                            <tr data-line="{{ $line->id }}" data-severity="{{ strtolower($severity->name) }}" class="{{ $severity->rowClasses() }}">
                                <td data-cell="ts" class="px-4 py-2 whitespace-nowrap tabular-nums">{{ $line->ts }}</td>
                                <td data-cell="level" class="px-4 py-2">{{ $line->level ?? '—' }}</td>
                                <td data-cell="event" class="px-4 py-2 whitespace-nowrap">{{ $line->event ?? '—' }}@if ($line->phase !== null) &middot; {{ $line->phase }}@endif</td>
                                <td data-cell="msg" class="px-4 py-2">
                                    {{ $line->msg ?? '—' }}
                                    @if ($line->data !== null)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-gray-500">data</summary>
                                            <pre class="mt-1 overflow-x-auto rounded bg-gray-50 dark:bg-gray-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->data)) !!}</pre>
                                        </details>
                                    @endif
                                    @if ($line->error !== null)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer text-gray-500">error</summary>
                                            <pre class="mt-1 overflow-x-auto rounded bg-gray-50 dark:bg-gray-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->error)) !!}</pre>
                                        </details>
                                    @endif
                                    <x-admin.log-ids :line="$line" :filters="$filters" :exclude="['request', 'actor']" />
                                </td>
                                <td data-cell="request" class="px-4 py-2 whitespace-nowrap">
                                    @if ($line->requestId === null)
                                        —
                                    @else
                                        <a href="{{ \App\Logging\Admin\LogFilterLinks::href('request', $line->requestId, $filters) }}" class="underline">{{ $line->requestId }}</a>
                                        <a href="{{ route('admin.logs.story', ['requestId' => $line->requestId]) }}" aria-label="Open request story for {{ $line->requestId }}" class="ml-1 inline-flex items-center rounded border border-gray-300 dark:border-gray-700 px-1.5 leading-5 text-gray-600 dark:text-gray-400 hover:border-gray-500 dark:hover:border-gray-500">&rsaquo;</a>
                                    @endif
                                </td>
                                <td data-cell="actor" class="px-4 py-2 whitespace-nowrap">
                                    <x-admin.log-actor :actor-type="$line->actorType" :actor-id="$line->actorId" :filters="$filters" />
                                </td>
                                <td data-cell="duration" class="px-4 py-2 text-right tabular-nums">{{ $line->durationMs ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-admin.pager :page="$page" base-url="{{ route('admin.logs.index') }}" :query="$filterQuery" />
        @endif
    @endif
</x-layouts.admin>
