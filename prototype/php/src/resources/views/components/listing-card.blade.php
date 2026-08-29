@props(['listing'])

@php
    use App\Domain\Listings\ListingStockLabel;
@endphp

<article class="flex h-full flex-col overflow-hidden rounded-card border border-line bg-surface">
    <a href="{{ route('shop.listing', $listing) }}" class="block">
        <img src="{{ $listing->imageUrl() }}" alt="{{ $listing->title }}"
             class="aspect-square w-full object-cover">
    </a>
    <div class="flex flex-1 flex-col gap-2 p-4">
        <p class="flex items-center gap-2 text-xs text-ink-faint">
            <x-ui.avatar :name="$listing->seller->displayName()" size="xs" />
            {{ $listing->seller->displayName() }}
        </p>
        <h2 class="font-display text-base leading-snug text-ink">
            <a href="{{ route('shop.listing', $listing) }}" class="hover:text-accent">{{ $listing->title }}</a>
        </h2>
        <p class="mt-auto flex items-center gap-2 pt-1">
            <span class="font-semibold text-ink">{{ $listing->price()->format() }}</span>
            <x-ui.badge>{{ ListingStockLabel::withInStock($listing->quantity) }}</x-ui.badge>
        </p>
    </div>
</article>
