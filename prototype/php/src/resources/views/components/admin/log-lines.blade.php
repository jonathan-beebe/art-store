{{-- One request's lines, oldest first: the story view's whole body, and
     what a `group=1` row opens into. Shared so the two never drift. --}}
@props(['lines', 'open' => false, 'filters' => []])

<ol class="mt-4 space-y-3">
    @foreach ($lines as $line)
        @php $severity = \App\Logging\Admin\LogSeverity::ofLevel($line->level); @endphp
        <li data-line="{{ $line->id }}" data-severity="{{ strtolower($severity->name) }}" class="rounded border border-gray-300 dark:border-gray-700 p-3 {{ $severity->rowClasses() !== '' ? $severity->rowClasses() : 'bg-white dark:bg-gray-900' }}">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span data-cell="msg" class="font-medium">{{ $line->msg ?? '—' }}</span>
                <span data-cell="level" class="rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs">{{ $line->level ?? '—' }}</span>
                <span data-cell="event" class="text-gray-600 dark:text-gray-400">{{ $line->event ?? '—' }}@if ($line->phase !== null) &middot; {{ $line->phase }}@endif</span>
                <span data-cell="ts" class="ml-auto tabular-nums text-gray-500">{{ $line->ts }}</span>
                @if ($line->durationMs !== null)
                    <span data-cell="duration" class="tabular-nums text-gray-500">{{ $line->durationMs }} ms</span>
                @endif
            </div>
            @if ($line->data !== null)
                <details @if ($open) open @endif class="mt-2">
                    <summary class="cursor-pointer text-gray-500">data</summary>
                    <pre class="mt-1 overflow-x-auto rounded bg-gray-50 dark:bg-gray-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->data)) !!}</pre>
                </details>
            @endif
            @if ($line->error !== null)
                <details @if ($open) open @endif class="mt-2">
                    <summary class="cursor-pointer text-gray-500">error</summary>
                    <pre class="mt-1 overflow-x-auto rounded bg-gray-50 dark:bg-gray-800/50 p-2 text-xs">{!! \App\Logging\Admin\LogIdLinks::linkify(\App\Logging\Admin\LogJson::pretty($line->error)) !!}</pre>
                </details>
            @endif
            <x-admin.log-ids :line="$line" :filters="$filters" />
        </li>
    @endforeach
</ol>
