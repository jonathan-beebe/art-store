{{-- An actor cell: the id as a monospaced pill filter link — the same
     anatomy the request cell uses — and, only where the id's prefix has a
     detail page, a chevron button to the record itself, styled identically
     to the story chevron. Admin ids have no detail page, so an admin actor
     never grows that chevron. `:truncate="true"` shows the prefix plus 8
     body characters (a collapsed row's concession) while the pill's href,
     `title`, and accessible name still carry the full id
     (`x-admin.log-id-chip`); the chevron's own
     `aria-label="View <type> <full id>"` is its accessible name — there is
     no separate visible type label, the pill's prefix already carries it. --}}
@props(['actorType', 'actorId', 'filters' => [], 'truncate' => false])

@if ($actorId === null)
    {{ $actorType ?? '—' }}
@else
    @php
        $entityHref = \App\Logging\Admin\LogIdLinks::hrefFor($actorId);
    @endphp
    <x-admin.log-id-chip :id="$actorId" :href="\App\Logging\Admin\LogFilterLinks::href('actor', $actorId, $filters)" :truncate="$truncate" />
    @if ($entityHref !== null)
        <a href="{{ $entityHref }}" aria-label="View {{ $actorType }} {{ $actorId }}" class="ml-1 inline-flex h-6 w-6 items-center justify-center rounded border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-500 dark:hover:border-gray-500">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
        </a>
    @endif
@endif
