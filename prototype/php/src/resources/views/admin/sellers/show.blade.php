<x-layouts.admin :title="$seller->displayName().' — Art Store admin'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">{{ $seller->displayName() }}</h1>
        <a href="{{ route('admin.sellers.index') }}" class="ml-auto text-gray-700 underline">All sellers</a>
    </div>

    <dl class="mt-4 rounded border border-gray-300 bg-white p-4">
        <dt class="text-gray-600">Email</dt>
        <dd class="mt-1">{{ $seller->email }}</dd>
        <dt class="mt-3 text-gray-600">Joined</dt>
        <dd class="mt-1">{{ $seller->created_at?->format('M j, Y') }}</dd>
    </dl>

    <section aria-labelledby="listings-heading" class="mt-6">
        <h2 id="listings-heading" class="font-semibold text-gray-700">Listings</h2>

        <dl class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($tally as $row)
                <div class="rounded border border-gray-300 bg-white p-4">
                    <dt class="text-gray-600">{{ $row->label() }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $row->count }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section aria-labelledby="fulfillments-heading" class="mt-6">
        <h2 id="fulfillments-heading" class="font-semibold text-gray-700">Fulfillments</h2>

        <div class="mt-2 rounded border border-gray-300 bg-white p-4">
            <p class="text-2xl font-semibold tabular-nums">{{ $fulfillmentCount }}</p>
        </div>
    </section>

    <section aria-labelledby="message-heading" class="mt-6 max-w-xl">
        <h2 id="message-heading" class="font-semibold text-gray-700">Message seller</h2>

        <x-messaging.body-form
            :action="route('admin.sellers.messages', $seller)"
            label="Message"
            class="mt-2 rounded border border-gray-300 bg-white p-4"
        />
    </section>
</x-layouts.admin>
