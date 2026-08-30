{{-- An actor cell: the type, the id as a filter link back into this list,
     and — only where the id's prefix has a detail page — a separate "View
     <type>" control to the record itself. Admin ids have no detail page,
     so an admin actor never grows that control. `:truncate="true"` shows
     the prefix plus 8 body characters (a collapsed row's concession) while
     the link's href, `title`, and accessible name still carry the full
     id. --}}
@props(['actorType', 'actorId', 'filters' => [], 'truncate' => false])

@if ($actorId === null)
    {{ $actorType ?? '—' }}
@else
    @php
        $entityHref = \App\Logging\Admin\LogIdLinks::hrefFor($actorId);
        $shown = $truncate ? \App\Logging\Admin\LogIdLinks::truncate($actorId) : $actorId;
    @endphp
    {{ $actorType }}
    <a href="{{ \App\Logging\Admin\LogFilterLinks::href('actor', $actorId, $filters) }}" title="{{ $actorId }}" aria-label="{{ $actorType }} {{ $actorId }}" class="underline">{{ $shown }}</a>
    @if ($entityHref !== null)
        <a href="{{ $entityHref }}" class="ml-1 text-xs text-gray-500 underline">View {{ $actorType }}</a>
    @endif
@endif
