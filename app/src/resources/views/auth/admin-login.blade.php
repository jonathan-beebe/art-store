<x-layouts.auth title="Sign in — Art Store admin" accent="stone">
    @if (session('sent_to'))
        <div role="status" class="mb-6 rounded-md border border-green-300 dark:border-green-900 bg-green-50 dark:bg-green-950/40 p-4 text-green-900 dark:text-green-200">
            <p class="font-semibold">Check your email</p>
            <p class="mt-1">If {{ session('sent_to') }} has an admin account, a sign-in link is on its way. It works
                once and expires in {{ config('magic_links.expiry_minutes') }} minutes.</p>
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="mb-6 rounded-md border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.admin.send') }}" class="space-y-6">
        @csrf

        <div>
            <label for="email" class="block text-sm/6 font-medium text-stone-900 dark:text-stone-100">Email address</label>
            <div class="mt-2">
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                       class="block w-full rounded-md bg-white px-3 py-1.5 text-sm text-stone-900 outline-1 -outline-offset-1 outline-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-white dark:outline-white/10">
            </div>
            @error('email')
                <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="flex w-full justify-center rounded-md bg-stone-700 px-3 py-1.5 text-sm font-semibold text-white shadow-xs hover:bg-stone-600">Email me a sign-in link</button>
    </form>
</x-layouts.auth>
