@extends('layouts.shop')

@section('title', 'Favorites — Art Store')

@section('content')
    <h1 class="text-4xl font-semibold tracking-tight">Favorites</h1>

    @if ($listings->isEmpty())
        <p class="mt-10 text-lg text-neutral-600">Nothing saved yet. Tap Favorite on any piece you want to come back to.</p>
    @else
        <ul class="mt-14 grid grid-cols-1 gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($listings as $listing)
                <li>@include('shop.partials.listing-card', ['listing' => $listing])</li>
            @endforeach
        </ul>
    @endif
@endsection
