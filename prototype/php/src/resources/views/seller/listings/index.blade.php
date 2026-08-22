@extends('layouts.seller')
@use('App\Domain\Reports\StatusLabel')

@section('title', 'Listings — Art Store seller')

@section('content')
    <div class="flex items-center gap-4">
        <h1 class="text-xl font-semibold">Listings</h1>
        <a href="{{ route('seller.listings.create') }}" class="ml-auto rounded bg-gray-900 px-4 py-2 font-medium text-white">New listing</a>
    </div>

    @if ($listings->isEmpty())
        <p class="mt-4 rounded border border-gray-300 bg-white p-4 text-gray-600">No listings yet. Start with a new one.</p>
    @else
        <div class="mt-4 overflow-x-auto rounded border border-gray-300 bg-white">
            <table class="w-full text-left">
                <caption class="sr-only">Your listings, newest first</caption>
                <thead class="border-b border-gray-300 bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-semibold">Listing</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Status</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Price</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Qty</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Views</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Favorites</th>
                        <th scope="col" class="px-4 py-2 text-right font-semibold">Cart adds</th>
                        <th scope="col" class="px-4 py-2 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($listings as $listing)
                        <tr>
                            <th scope="row" class="px-4 py-3 font-normal">
                                <span class="flex items-center gap-3">
                                    <img src="{{ $listing->imageUrl() }}" alt="" width="48" height="48" class="h-12 w-12 rounded object-cover">
                                    <a href="{{ route('seller.listings.show', $listing->id) }}" class="font-medium underline">{{ $listing->title }}</a>
                                </span>
                            </th>
                            <td class="px-4 py-3">{{ StatusLabel::of($listing->status) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $listing->price()->format() }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $listing->quantity }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $listing->views_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $listing->favorites_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $listing->cart_adds_count }}</td>
                            <td class="px-4 py-3">
                                <span class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('seller.listings.edit', $listing->id) }}" class="rounded border border-gray-400 px-2 py-1">Edit</a>

                                    @foreach ($listing->status->transitions() as $next)
                                        <form method="POST" action="{{ route('seller.listings.status', $listing->id) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="{{ $next->value }}">
                                            <button type="submit" class="rounded border border-gray-400 px-2 py-1">Mark {{ lcfirst(StatusLabel::of($next)) }}</button>
                                        </form>
                                    @endforeach
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
