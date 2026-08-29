@php
    // The cover photo plus a thumbnail row for the rest — shared by
    // `/art/{slug}` and the seller's buyer-view panel (IMPRV-015), so a
    // rendering-rule change (the placeholder, the thumbnail cap) lands on
    // both from here. `$compact` only scales the corner radius and gaps for
    // the panel's 380px column.
    $compact ??= false;
@endphp

<img src="{{ $listing->imageUrl() }}" alt="{{ $listing->title }}"
     class="aspect-square w-full rounded-{{ $compact ? 'lg' : '3xl' }} object-cover">

@if ($listing->images->count() > 1)
    <div class="mt-{{ $compact ? '2' : '4' }} grid grid-cols-4 gap-{{ $compact ? '1' : '3' }}">
        @foreach ($listing->images->skip(1)->values() as $image)
            <img src="{{ $image->url() }}" alt="{{ $listing->title }} — photo {{ $loop->iteration + 1 }}"
                 class="aspect-square w-full rounded-{{ $compact ? 'md' : 'xl' }} object-cover">
        @endforeach
    </div>
@endif
