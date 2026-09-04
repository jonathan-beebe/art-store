<x-layouts.shop :title="$profile->name.' — Art Store'" :description="$description" :image="$ogImage">
    @if ($isOwnStore && ! $profile->isPublished())
        <x-ui.alert tone="notice" class="mb-10">
            <p>This store is hidden. You are the only one who can open this page — publish it from
                <a href="{{ route('seller.store.show') }}" class="underline">your store settings</a>.</p>
        </x-ui.alert>
    @endif

    <x-store.profile :profile="$profile" :facts="$facts" />

    <section aria-labelledby="store-listings-heading" class="mt-16">
        <h2 id="store-listings-heading" class="font-display text-2xl text-ink">Work by {{ $profile->name }}</h2>

        @include('shop.partials.listing-grid', [
            'listings' => $listings,
            'emptyMessage' => 'Nothing is for sale here yet.',
        ])
    </section>
</x-layouts.shop>
