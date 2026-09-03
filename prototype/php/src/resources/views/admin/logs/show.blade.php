<x-layouts.admin :title="'Request '.$requestId.' — Art Store admin'" mode="content-wide">
    <div class="flex flex-wrap items-center gap-2 text-sm text-stone-600 dark:text-stone-400">
        <a href="{{ route('admin.logs.index') }}" class="inline-flex min-h-11 items-center gap-1.5 hover:text-stone-900 dark:hover:text-stone-100">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            <span>Logs</span>
        </a>
        <span class="text-stone-300 dark:text-stone-700">/</span>
        <h1 class="font-mono text-base font-semibold text-stone-900 dark:text-stone-100">Request <a data-request-id href="{{ \App\Logging\Admin\LogFilterLinks::href('request', $requestId) }}" class="underline">{{ $requestId }}</a></h1>
    </div>

    @if (! $storeAvailable)
        <x-admin.nothing class="mt-4">The log store is unavailable — <code>LOG_DATABASE_FILE</code> is off or the store failed to open. Every line still goes to stdout.</x-admin.nothing>
    @elseif (count($lines) === 0)
        <x-admin.nothing class="mt-4">No lines are stored for this request. It may be outside the retention window.</x-admin.nothing>
    @else
        @php
            $severity = \App\Logging\Admin\LogSeverity::worstOf($lines);
            $tint = \App\Logging\Admin\LogDurationTint::ofMs($header->durationMs);
        @endphp

        {{-- Request header card: tinted by the worst line in the story --}}
        <div data-severity="{{ strtolower($severity->name) }}" class="mt-4 flex flex-col gap-3 rounded-lg border p-4 {{ $severity->borderClasses() }} {{ $severity->rowClasses() !== '' ? $severity->rowClasses() : 'bg-white dark:bg-stone-900' }}">
            <div class="flex flex-wrap items-center gap-3">
                @if ($header->method !== null || $header->path !== null)
                    <span class="font-mono text-base font-semibold">{{ $header->method }} {{ $header->path }}</span>
                @endif
                @if ($header->status !== null)
                    <span class="inline-flex rounded-md px-2 py-0.5 font-mono text-sm font-semibold {{ $header->status >= 400 ? 'bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-300' : 'bg-stone-100 dark:bg-stone-800 text-stone-700 dark:text-stone-300' }}">{{ $header->status }}</span>
                @endif
                @if ($header->durationMs !== null)
                    <span data-stat="duration" class="font-mono text-sm tabular-nums {{ $tint?->textClasses() ?? 'text-stone-500 dark:text-stone-400' }}">{{ $header->durationMs }} ms</span>
                @endif
                <span class="flex-1"></span>
                <span class="text-xs text-stone-500 dark:text-stone-400" data-stat="lines">{{ $totalCount }} line{{ $totalCount === 1 ? '' : 's' }} &middot; {{ $header->firstTs }} &rarr; {{ $header->lastTs }} UTC</span>
            </div>

            <x-admin.log-filter-rail
                :txn-id="$header->txnId"
                :session-id="$header->sessionId"
                :actor-type="$header->actorType"
                :actor-id="$header->actorId"
            />
        </div>

        @if ($totalCount > count($lines))
            <p data-cap-notice class="mt-4 rounded-lg border border-amber-500 bg-white dark:bg-stone-900 p-3 text-amber-700 dark:text-amber-400">
                Showing the first {{ $lineCap }} of {{ $totalCount }} lines.
            </p>
        @endif

        <div class="mt-4 shrink-0 overflow-hidden rounded-lg border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
            <x-admin.log-lines :lines="$lines" :open="true" :headers="true" />
        </div>
    @endif
</x-layouts.admin>
