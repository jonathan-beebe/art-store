<x-layouts.admin :title="$customer->displayName().' — Art Store admin'">
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">{{ $customer->displayName() }}</h1>
        <a href="{{ route('admin.customers.index') }}" class="ml-auto text-gray-700 underline">All customers</a>
    </div>

    <dl class="mt-4 rounded border border-gray-300 bg-white p-4">
        <dt class="text-gray-600">Email</dt>
        <dd class="mt-1">{{ $customer->email ?? '—' }}</dd>
        <dt class="mt-3 text-gray-600">Joined</dt>
        <dd class="mt-1">{{ $customer->created_at?->format('M j, Y') }}</dd>
    </dl>

    <section aria-labelledby="standing-heading" class="mt-6 max-w-xl">
        <h2 id="standing-heading" class="font-semibold text-gray-700">Standing</h2>

        @if ($customer->activeBlock)
            <dl class="mt-2 rounded border border-red-300 bg-red-50 p-4 text-red-900">
                <dt class="font-semibold">Blocked</dt>
                <dd class="mt-1">{{ $customer->activeBlock->reason }}</dd>
                <dd class="mt-1 text-red-700">Since {{ $customer->activeBlock->created_at?->format('M j, Y g:ia') }}</dd>
            </dl>

            <form method="POST" action="{{ route('admin.customers.blocks.lift', $customer) }}" class="mt-2">
                @csrf
                <button type="submit" class="rounded bg-gray-900 px-4 py-2 font-medium text-white">Lift block</button>
            </form>
        @else
            <p class="mt-2 rounded border border-gray-300 bg-white p-4 text-gray-600">Not blocked.</p>

            <form method="POST" action="{{ route('admin.customers.blocks.store', $customer) }}"
                  class="mt-2 rounded border border-gray-300 bg-white p-4">
                @csrf

                <label for="reason" class="block font-medium text-gray-700">Reason</label>
                <input id="reason" name="reason" type="text" required maxlength="500" value="{{ old('reason') }}"
                       class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
                @error('reason')
                    <p class="mt-1 text-red-700">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-4 rounded bg-gray-900 px-4 py-2 font-medium text-white">Block</button>
            </form>
        @endif
    </section>

    <section aria-labelledby="block-history-heading" class="mt-6">
        <h2 id="block-history-heading" class="font-semibold text-gray-700">Block history</h2>

        @if ($customer->blocks->isEmpty())
            <x-admin.nothing>Never blocked.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Every block this customer has been under</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Reason</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Blocked</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Lifted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($customer->blocks as $block)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">{{ $block->reason }}</th>
                                <td class="px-4 py-2">{{ $block->created_at?->format('M j, Y g:ia') }}</td>
                                <td class="px-4 py-2">{{ $block->lifted_at?->format('M j, Y g:ia') ?? 'Active' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="merges-heading" class="mt-6">
        <h2 id="merges-heading" class="font-semibold text-gray-700">Merge history</h2>

        @if ($customer->mergesAsCustomer->isEmpty() && $customer->mergesAsAnonymous->isEmpty())
            <x-admin.nothing>No merges.</x-admin.nothing>
        @else
            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Anonymous visitors folded into this account, and the account this row was folded into</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Direction</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Other customer</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Merged</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($customer->mergesAsCustomer as $merge)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">Absorbed</th>
                                <td class="px-4 py-2">
                                    <a href="{{ route('admin.customers.show', $merge->anonymous_customer_id) }}" class="underline">{{ $merge->anonymous_customer_id }}</a>
                                </td>
                                <td class="px-4 py-2">{{ $merge->created_at?->format('M j, Y g:ia') }}</td>
                            </tr>
                        @endforeach
                        @foreach ($customer->mergesAsAnonymous as $merge)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">Folded into</th>
                                <td class="px-4 py-2">
                                    <a href="{{ route('admin.customers.show', $merge->customer_id) }}" class="underline">{{ $merge->customer_id }}</a>
                                </td>
                                <td class="px-4 py-2">{{ $merge->created_at?->format('M j, Y g:ia') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section aria-labelledby="orders-heading" class="mt-6">
        <h2 id="orders-heading" class="font-semibold text-gray-700">Orders</h2>

        <x-admin.orders-table :orders="$customer->orders" :show-customer="false" caption="Orders this customer placed" />
    </section>

    <section aria-labelledby="favorites-heading" class="mt-6">
        <h2 id="favorites-heading" class="font-semibold text-gray-700">Favorites</h2>

        @if ($customer->favorites->isEmpty())
            <x-admin.nothing>No favorites.</x-admin.nothing>
        @else
            <ul class="mt-2 divide-y divide-gray-200 rounded border border-gray-300 bg-white">
                @foreach ($customer->favorites as $favorite)
                    <li class="px-4 py-2">
                        <a href="{{ route('admin.listings.show', $favorite->listing) }}" class="underline">{{ $favorite->listing->title }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="cart-heading" class="mt-6">
        <h2 id="cart-heading" class="font-semibold text-gray-700">Cart</h2>

        @if ($customer->cartItems->isEmpty())
            <x-admin.nothing>Cart is empty.</x-admin.nothing>
        @else
            <ul class="mt-2 divide-y divide-gray-200 rounded border border-gray-300 bg-white">
                @foreach ($customer->cartItems as $item)
                    <li class="flex justify-between px-4 py-2">
                        <a href="{{ route('admin.listings.show', $item->listing) }}" class="underline">{{ $item->listing->title }}</a>
                        <span class="tabular-nums">&times;{{ $item->quantity }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="message-heading" class="mt-6 max-w-xl">
        <h2 id="message-heading" class="font-semibold text-gray-700">Message customer</h2>

        <x-messaging.body-form
            :action="route('admin.customers.messages', $customer)"
            label="Message"
            class="mt-2 rounded border border-gray-300 bg-white p-4"
        />
    </section>
</x-layouts.admin>
