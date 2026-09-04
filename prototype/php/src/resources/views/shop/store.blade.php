<x-layouts.shop :title="$page->profile->name.' — Art Store'" :description="$page->description" :image="$page->ogImage">
    @if ($page->isOwnStore && ! $page->profile->isPublished())
        <x-ui.alert tone="notice" class="mb-10">
            <p>This store is hidden. You are the only one who can open this page — publish it from
                <a href="{{ route('seller.store.show') }}" class="underline">your store settings</a>.</p>
        </x-ui.alert>
    @endif

    <x-store.profile :profile="$page->profile" :facts="$page->facts" />

    <section aria-labelledby="store-listings-heading" class="mt-16">
        <h2 id="store-listings-heading" class="font-display text-2xl text-ink">Work by {{ $page->profile->name }}</h2>

        @include('shop.partials.listing-grid', [
            'listings' => $page->listings,
            'emptyMessage' => 'Nothing is for sale here yet.',
        ])
    </section>
</x-layouts.shop>
