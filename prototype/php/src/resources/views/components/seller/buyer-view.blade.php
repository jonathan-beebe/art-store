@php
    use App\Domain\Listings\ListingStockLabel;

    // IMPRV-015: the panel and `/art/{slug}` share the images, description,
    // and configurator partials outright — this file only supplies the
    // frame (the dashed border, the caption), the compact title, and the
    // one branch no shared partial owns: the unconfigured listing's
    // price-and-stock line.
    $mode = $interactive ? 'preview' : 'static';
@endphp

<div class="relative rounded-lg border border-dashed border-neutral-400 bg-white p-5 text-neutral-900">
    <span class="absolute -top-3 left-4 rounded-full bg-neutral-800 px-3 py-0.5 text-xs font-medium text-white">What buyers see @if ($caption !== null)— {{ $caption }}@endif</span>

    @include('shop.partials.listing-images', ['listing' => $listing, 'compact' => true])

    <p class="mt-3 text-base font-semibold text-neutral-900">{{ $listing->title }}</p>

    @include('shop.partials.listing-description', ['listing' => $listing, 'compact' => true])

    @if (! $hasConfigurator)
        <p class="mt-3 text-2xl font-semibold text-neutral-900">{{ $listing->price()->format() }}</p>
        <p class="mt-1 text-sm text-neutral-500">{{ ListingStockLabel::withInStock($listing->quantity) }}</p>
        <div class="mt-4">
            @include('shop.partials.add-to-cart-button', ['mode' => $mode, 'listing' => $listing, 'standalone' => true])
        </div>
    @else
        @include('shop.partials.configurator', [
            'listing' => $listing,
            'configuration' => $configuration,
            'focusId' => $focusId,
            'mode' => $mode,
            'refreshUrl' => $refreshUrl,
        ])
    @endif
</div>
