<x-layouts.shop title="Sign in — Art Store">
    <h1 class="text-4xl font-semibold tracking-tight">Sign in</h1>

    @if (session('sent_to'))
        <div role="status" class="mt-8 max-w-xl rounded-lg border border-green-200 bg-green-50 p-6 text-green-900">
            <p class="text-lg font-semibold">Check your email</p>
            <p class="mt-2">A sign-in link is on its way to {{ session('sent_to') }}. It works once and expires in
                {{ config('magic_links.expiry_minutes') }} minutes.</p>
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="mt-8 max-w-xl rounded-lg border border-red-200 bg-red-50 p-6 text-red-900">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('auth.customer.send') }}" class="mt-8 max-w-md">
        @csrf

        @if ($redirectTo)
            <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
        @endif

        <label for="email" class="block text-sm font-medium text-neutral-700">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
               class="mt-2 block w-full rounded-lg border border-neutral-300 px-4 py-3 text-lg">

        @error('email')
            <p class="mt-2 text-red-700">{{ $message }}</p>
        @enderror

        <button type="submit" class="mt-6 rounded-lg bg-neutral-900 px-6 py-3 font-medium text-white">Email me a sign-in link</button>
    </form>

    <p class="mt-6 max-w-md text-neutral-600">No password, no account to create. Anything already in your cart comes with you.</p>
</x-layouts.shop>
