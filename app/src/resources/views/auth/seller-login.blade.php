<x-layouts.auth title="Sign in — Art Store seller" accent="indigo">
    @if (session('sent_to'))
        <div role="status" class="mb-6 rounded-md border border-green-300 dark:border-green-900 bg-green-50 dark:bg-green-950/40 p-4 text-green-900 dark:text-green-200">
            <p class="font-semibold">Check your email</p>
            <p class="mt-1">A sign-in link is on its way to {{ session('sent_to') }}. It works once and expires in
                {{ config('magic_links.expiry_minutes') }} minutes.</p>
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="mb-6 rounded-md border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.seller.send') }}" class="space-y-6">
        @csrf

        <div>
            <label for="email" class="block text-sm/6 font-medium text-gray-900 dark:text-gray-100">Email address</label>
            <div class="mt-2">
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                       class="block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
            </div>
            @error('email')
                <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Email me a sign-in link</button>
    </form>

    <p class="mt-10 text-center text-sm/6 text-gray-500 dark:text-gray-400">No password. Selling for the first time? The link creates your shop.</p>
</x-layouts.auth>
