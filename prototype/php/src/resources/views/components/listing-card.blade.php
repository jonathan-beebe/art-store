@props(['listing'])

<article>
    <a href="{{ route('shop.listing', $listing) }}" class="block">
        <img src="{{ $listing->imageUrl() }}" alt="{{ $listing->title }}"
             class="aspect-square w-full rounded-2xl object-cover">
        <h2 class="mt-4 text-lg font-medium">{{ $listing->title }}</h2>
    </a>
    <p class="mt-1 text-sm text-neutral-500">{{ $listing->seller->displayName() }}</p>
    <p class="mt-2 text-base text-neutral-900">{{ $listing->price()->format() }}</p>
</article>
