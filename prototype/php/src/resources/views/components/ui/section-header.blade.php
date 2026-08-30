@props(['title', 'headingId' => null, 'linkHref' => null, 'linkLabel' => null])

{{--
    A section's title and its optional "see all" link (DSGN-007) — every
    invitation on the home page opens with one. `headingId` is what the
    section's own `aria-labelledby` points at; the link is left out entirely
    when a section has nowhere further to send a visitor, rather than
    pointing at a page that doesn't exist.
--}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-baseline gap-x-4 gap-y-2']) }}>
    <h2 @if ($headingId !== null) id="{{ $headingId }}" @endif class="font-display text-xl text-ink">{{ $title }}</h2>
    @if ($linkHref !== null)
        <a href="{{ $linkHref }}" class="ml-auto text-sm font-semibold text-accent hover:text-accent-strong">{{ $linkLabel }}</a>
    @endif
</div>
