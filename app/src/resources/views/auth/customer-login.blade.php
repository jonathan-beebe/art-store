<x-layouts.shop title="Sign in — Art Store">
    <h1 class="font-display text-4xl leading-tight text-ink">Sign in</h1>

    @if (session('sent_to'))
        <x-ui.alert tone="success" class="mt-8 max-w-xl">
            <p class="text-lg font-semibold">Check your email</p>
            <p class="mt-2">A sign-in link is on its way to {{ session('sent_to') }}. It works once and expires in
                {{ config('magic_links.expiry_minutes') }} minutes.</p>
        </x-ui.alert>
    @endif

    @if (session('error'))
        <x-ui.alert tone="danger" class="mt-8 max-w-xl">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('auth.customer.send') }}" class="mt-8 max-w-md">
        @csrf

        @if ($redirectTo)
            <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
        @endif

        <x-ui.label for="email">Email address</x-ui.label>
        <x-ui.input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-2 text-lg" />

        @error('email')
            <p class="mt-2 text-danger">{{ $message }}</p>
        @enderror

        <x-ui.button variant="primary" class="mt-6">Email me a sign-in link</x-ui.button>
    </form>

    <p class="mt-6 max-w-md text-ink-muted">No password, no account to create. Anything already in your cart comes with you.</p>
</x-layouts.shop>
