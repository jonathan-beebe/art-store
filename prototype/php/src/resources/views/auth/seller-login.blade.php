<x-layouts.seller title="Sign in — Art Store seller">
    <h1 class="text-xl font-semibold">Sign in</h1>

    @if (session('sent_to'))
        <div role="status" class="mt-4 rounded border border-green-300 dark:border-green-900 bg-green-50 dark:bg-green-950/40 p-4 text-green-900 dark:text-green-200">
            <p class="font-semibold">Check your email</p>
            <p class="mt-1">A sign-in link is on its way to {{ session('sent_to') }}. It works once and expires in
                {{ config('magic_links.expiry_minutes') }} minutes.</p>
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="mt-4 rounded border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.seller.send') }}" class="mt-4 max-w-md rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf

        <label for="email" class="block font-medium text-gray-700 dark:text-gray-300">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
               class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">

        @error('email')
            <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror

        <button type="submit" class="mt-4 rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Email me a sign-in link</button>
    </form>

    <p class="mt-4 text-gray-600 dark:text-gray-400">No password. Selling for the first time? The link creates your shop.</p>
</x-layouts.seller>
