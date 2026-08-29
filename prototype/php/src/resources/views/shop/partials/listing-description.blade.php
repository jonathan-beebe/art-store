@php
    // The description paragraph and the typed sections beneath it — shared
    // by `/art/{slug}` and the seller's buyer-view panel (IMPRV-015).
    // `$compact` only scales the paragraph and drops the section heading
    // styling down to the shared partial's own defaults, for the panel's
    // 380px column.
    $compact ??= false;
@endphp

<p class="mt-{{ $compact ? '2' : '8' }} text-{{ $compact ? 'sm' : 'lg' }} leading-relaxed text-neutral-700">{{ $listing->description }}</p>

@if ($listing->descriptionSections->isNotEmpty())
    @include('shop.partials.description-sections', array_merge(['sections' => $listing->descriptionSections], $compact ? [] : [
        'sectionClass' => 'mt-14 border-t border-neutral-100 pt-10',
        'headingTag' => 'h2',
        'headingClass' => 'text-xl font-semibold tracking-tight',
    ]))
@endif
