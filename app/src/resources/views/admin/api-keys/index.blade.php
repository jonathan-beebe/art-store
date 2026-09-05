<x-layouts.admin title="API keys — Art Store admin">
    <h1 class="text-xl font-semibold">API keys</h1>
    <p class="mt-1 max-w-prose text-stone-600 dark:text-stone-400">
        A key lets an agent such as Claude Code query the log store and the analytics store through the MCP endpoint at <code class="rounded bg-stone-100 dark:bg-stone-800 px-1">POST /mcp</code>, as you. Each key reads everything the admin site reads. Only its digest is stored, so a key is shown once, when minted.
    </p>

    @if ($mintedKey !== null)
        <section aria-labelledby="minted-heading" class="mt-4 rounded border border-amber-300 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/40 p-4">
            <h2 id="minted-heading" class="font-semibold text-amber-900 dark:text-amber-200">Your new key</h2>
            <p class="mt-1 text-amber-900 dark:text-amber-200">Copy it now. It will not be shown again.</p>
            <output class="mt-2 block select-all overflow-x-auto rounded bg-white dark:bg-stone-900 px-3 py-2 font-mono text-sm text-stone-900 dark:text-stone-100">{{ $mintedKey }}</output>
            <p class="mt-2 text-sm text-amber-900 dark:text-amber-200">Connect Claude Code with <code class="font-mono">claude mcp add --transport http art-store {{ route('mcp') }} --header "Authorization: Bearer &lt;key&gt;"</code></p>
        </section>
    @endif

    <section aria-labelledby="mint-heading" class="mt-6">
        <h2 id="mint-heading" class="font-semibold text-stone-700 dark:text-stone-300">Mint a key</h2>
        <form method="POST" action="{{ route('admin.api-keys.store') }}"
              class="mt-2 rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
            @csrf
            <x-admin.input id="name" name="name" label="What the key is for" :value="old('name')" placeholder="Claude Code on my laptop" />
            @error('name')
                <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror
            <div class="mt-4">
                <x-admin.button-primary>Mint key</x-admin.button-primary>
            </div>
        </form>
    </section>

    <section aria-labelledby="keys-heading" class="mt-6">
        <h2 id="keys-heading" class="font-semibold text-stone-700 dark:text-stone-300">Your keys</h2>

        @if ($keys->isEmpty())
            <x-admin.nothing>No keys yet.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
                <table class="w-full text-left">
                    <caption class="sr-only">Your api keys, newest first</caption>
                    <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Name</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Id</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Minted</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Last used</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap">Status</th>
                            <th scope="col" class="px-4 py-2 font-semibold whitespace-nowrap"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                        @foreach ($keys as $key)
                            <tr>
                                <td class="px-4 py-2 font-medium">{{ $key->name }}</td>
                                <td class="px-4 py-2 font-mono text-sm text-stone-600 dark:text-stone-400">{{ $key->id }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-stone-600 dark:text-stone-400">{{ $key->created_at?->format('Y-m-d H:i') }} UTC</td>
                                <td class="px-4 py-2 whitespace-nowrap text-stone-600 dark:text-stone-400">{{ $key->last_used_at === null ? 'never' : $key->last_used_at->format('Y-m-d H:i').' UTC' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    @if ($key->isRevoked())
                                        <span class="text-stone-500">Revoked {{ $key->revoked_at?->format('Y-m-d') }}</span>
                                    @else
                                        <span class="text-green-800 dark:text-green-300">Active</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    @unless ($key->isRevoked())
                                        <form method="POST" action="{{ route('admin.api-keys.revoke', $key) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-stone-700 dark:text-stone-300 underline">Revoke</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.admin>
