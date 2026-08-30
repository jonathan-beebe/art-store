{{-- "Filter by" — a request's identifying ids as filter links back into
     the log list, plus a labeled link to the actor's own admin page when
     it has one. Shared by an expanded grouped row and the story header, so
     the two never drift. Ids render in full here — truncation is a
     collapsed row's concession, not this rail's. --}}
@props(['requestId' => null, 'txnId' => null, 'sessionId' => null, 'actorType' => null, 'actorId' => null])

@php
    $actorHref = $actorId === null ? null : \App\Logging\Admin\LogIdLinks::hrefFor($actorId);
@endphp

@if ($requestId !== null || $txnId !== null || $sessionId !== null || $actorId !== null)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-400']) }}>
        <span>Filter by</span>
        @if ($requestId !== null)
            <x-admin.log-id-chip :id="$requestId" :href="route('admin.logs.index', ['request' => $requestId])" :truncate="false" />
        @endif
        @if ($txnId !== null)
            <x-admin.log-id-chip :id="$txnId" :href="route('admin.logs.index', ['txn' => $txnId])" :truncate="false" />
        @endif
        @if ($sessionId !== null)
            <x-admin.log-id-chip :id="$sessionId" :href="route('admin.logs.index', ['session' => $sessionId])" :truncate="false" />
        @endif
        @if ($actorId !== null)
            <x-admin.log-id-chip :id="$actorId" :href="route('admin.logs.index', ['actor' => $actorId])" :truncate="false" />
        @endif
        @if ($actorHref !== null)
            <span class="text-gray-300 dark:text-gray-700">|</span>
            <a href="{{ $actorHref }}" class="text-gray-700 dark:text-gray-300 underline">View {{ $actorType }}</a>
        @endif
    </div>
@endif
