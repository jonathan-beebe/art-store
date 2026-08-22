@extends('layouts.seller')

@section('title', 'Sign in — Art Store seller')

@section('content')
    <h1 class="text-xl font-semibold">Sign in</h1>

    @if (session('sent_to'))
        <div role="status" class="mt-4 rounded border border-green-300 bg-green-50 p-4 text-green-900">
            <p class="font-semibold">Check your email</p>
            <p class="mt-1">A sign-in link is on its way to {{ session('sent_to') }}. It works once and expires in
                {{ config('magic_links.expiry_minutes') }} minutes.</p>
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="mt-4 rounded border border-red-300 bg-red-50 p-4 text-red-900">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.seller.send') }}" class="mt-4 max-w-md rounded border border-gray-300 bg-white p-4">
        @csrf

        <label for="email" class="block font-medium text-gray-700">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
               class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">

        @error('email')
            <p class="mt-1 text-red-700">{{ $message }}</p>
        @enderror

        <button type="submit" class="mt-4 rounded bg-gray-900 px-4 py-2 font-medium text-white">Email me a sign-in link</button>
    </form>

    <p class="mt-4 text-gray-600">No password. Selling for the first time? The link creates your shop.</p>
@endsection
