@extends('layouts.shop')

@section('title', $listing->title.' — Art Store')

@section('content')
    <article class="grid gap-12 lg:grid-cols-2">
        <img src="{{ $listing->imageUrl() }}" alt="{{ $listing->title }}"
             class="aspect-square w-full rounded-3xl object-cover">

        <div class="max-w-lg">
            <h1 class="text-4xl font-semibold leading-tight tracking-tight">{{ $listing->title }}</h1>
            <p class="mt-3 text-lg text-neutral-600">{{ $listing->seller->displayName() }}</p>
            <p class="mt-8 text-2xl">{{ $listing->price()->format() }}</p>

            <dl class="mt-8 grid grid-cols-2 gap-y-4 border-y border-neutral-100 py-6 text-base">
                <dt class="text-neutral-500">Medium</dt>
                <dd>{{ $listing->medium ?? 'Mixed' }}</dd>
                <dt class="text-neutral-500">Dimensions</dt>
                <dd>{{ $listing->dimensions ?? 'Unlisted' }}</dd>
                <dt class="text-neutral-500">Available</dt>
                <dd>{{ $isPurchasable ? $listing->quantity : 'Sold' }}</dd>
            </dl>

            <p class="mt-8 text-lg leading-relaxed text-neutral-700">{{ $listing->description }}</p>

            <div class="mt-10 flex flex-wrap items-center gap-4">
                @if ($isPurchasable)
                    <form method="POST" action="{{ route('shop.cart.add', $listing) }}">
                        @csrf
                        <button type="submit" class="rounded-full bg-neutral-900 px-8 py-3 text-base font-medium text-white">
                            Add to cart
                        </button>
                    </form>
                @endif

                <form method="POST" action="{{ route('shop.favorites.toggle', $listing) }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-neutral-300 px-8 py-3 text-base font-medium hover:border-neutral-900">
                        {{ $isFavorited ? 'Remove from favorites' : 'Favorite' }}
                    </button>
                </form>
            </div>
        </div>
    </article>
@endsection
