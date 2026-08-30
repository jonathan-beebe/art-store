{{-- "Filter by" — a request's identifying ids as filter links back into
     the log list (`App\Logging\Admin\LogFilterLinks`, carrying the
     caller's current filters). The actor id renders through
     `x-admin.log-actor` itself, so it carries the same pill-plus-chevron
     anatomy everywhere an actor appears. Shared by an expanded grouped row
     and the story header, so the two never drift. Ids render in full
     here — truncation is a collapsed row's concession, not this rail's. --}}
@props(['requestId' => null, 'txnId' => null, 'sessionId' => null, 'actorType' => null, 'actorId' => null, 'filters' => []])

@if ($requestId !== null || $txnId !== null || $sessionId !== null || $actorId !== null)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-400']) }}>
        <span>Filter by</span>
        @if ($requestId !== null)
            <x-admin.log-id-chip :id="$requestId" :href="\App\Logging\Admin\LogFilterLinks::href('request', $requestId, $filters)" :truncate="false" />
        @endif
        @if ($txnId !== null)
            <x-admin.log-id-chip :id="$txnId" :href="\App\Logging\Admin\LogFilterLinks::href('txn', $txnId, $filters)" :truncate="false" />
        @endif
        @if ($sessionId !== null)
            <x-admin.log-id-chip :id="$sessionId" :href="\App\Logging\Admin\LogFilterLinks::href('session', $sessionId, $filters)" :truncate="false" />
        @endif
        @if ($actorId !== null)
            <x-admin.log-actor :actor-type="$actorType" :actor-id="$actorId" :filters="$filters" :truncate="false" />
        @endif
    </div>
@endif
