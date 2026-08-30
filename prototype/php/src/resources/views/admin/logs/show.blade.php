<x-layouts.admin :title="'Request '.$requestId.' — Art Store admin'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Request <a data-request-id href="{{ \App\Logging\Admin\LogFilterLinks::href('request', $requestId) }}" class="underline">{{ $requestId }}</a></h1>
        <a href="{{ route('admin.logs.index', ['request' => $requestId]) }}" class="underline">Open in the log list</a>
    </div>

    @if (! $storeAvailable)
        <x-admin.nothing class="mt-4">The log store is unavailable — <code>LOG_DATABASE_FILE</code> is off or the store failed to open. Every line still goes to stdout.</x-admin.nothing>
    @elseif (count($lines) === 0)
        <x-admin.nothing class="mt-4">No lines are stored for this request. It may be outside the retention window.</x-admin.nothing>
    @else
        @php $severity = \App\Logging\Admin\LogSeverity::worstOf($lines); @endphp
        <dl data-severity="{{ strtolower($severity->name) }}" class="mt-4 grid grid-cols-1 gap-3 rounded border border-gray-300 dark:border-gray-700 p-1 sm:grid-cols-3 {{ $severity->rowClasses() }}">
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" data-stat="lines">
                <dt class="text-gray-600 dark:text-gray-400">Lines</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ $totalCount }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" data-stat="first">
                <dt class="text-gray-600 dark:text-gray-400">First line</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ $header->firstTs ?? '—' }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" data-stat="last">
                <dt class="text-gray-600 dark:text-gray-400">Last line</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ $header->lastTs ?? '—' }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" data-stat="duration">
                <dt class="text-gray-600 dark:text-gray-400">Duration (ms)</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ $header->durationMs ?? '—' }}</dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" data-stat="session">
                <dt class="text-gray-600 dark:text-gray-400">Session</dt>
                <dd class="mt-1 text-lg font-semibold">
                    @if ($header->sessionId === null)
                        —
                    @else
                        {!! \App\Logging\Admin\LogIdLinks::linkify($header->sessionId) !!}
                    @endif
                </dd>
            </div>
            <div class="rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" data-stat="actor">
                <dt class="text-gray-600 dark:text-gray-400">Actor</dt>
                <dd class="mt-1 text-lg font-semibold">
                    <x-admin.log-actor :actor-type="$header->actorType" :actor-id="$header->actorId" />
                </dd>
            </div>
        </dl>

        @if ($totalCount > count($lines))
            <p data-cap-notice class="mt-4 rounded border border-amber-500 bg-white dark:bg-gray-900 p-3 text-amber-700 dark:text-amber-400">
                Showing the first {{ $lineCap }} of {{ $totalCount }} lines.
            </p>
        @endif

        <x-admin.log-lines :lines="$lines" :open="true" />
    @endif
</x-layouts.admin>
