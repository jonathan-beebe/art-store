<x-layouts.admin :title="$customer->displayName().' — Art Store admin'" mode="detail">
    <x-slot:cells>
        <div class="flex items-baseline gap-2 border-b border-stone-200 p-3 dark:border-stone-800">
            <h1 class="text-sm font-semibold">Customers</h1>
            <span class="text-xs text-stone-500 dark:text-stone-400">{{ $cellCustomersTotal }}</span>
        </div>
        <div class="flex-1 overflow-y-auto">
            <x-admin.customers-cells :customers="$cellCustomers" :selected="$customer" />
        </div>
        <x-admin.cell-footer :shown="$cellCustomers->count()" :total="$cellCustomersTotal" :route="route('admin.customers.index')" />
    </x-slot:cells>

    @php
        $tint = match (true) {
            (bool) $customer->activeBlock => 'bad',
            $customer->isAnonymous() => 'neutral',
            $customer->isVerified() => 'ok',
            default => 'warn',
        };
        $standingLabel = match (true) {
            (bool) $customer->activeBlock => 'Blocked',
            $customer->isAnonymous() => 'Anonymous',
            $customer->isVerified() => 'Verified',
            default => 'Unverified',
        };
    @endphp

    <x-admin.back-link :route="route('admin.customers.index')" label="Customers" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1">
            <p class="text-xs text-stone-500 dark:text-stone-400">{{ $customer->id }} &middot; joined {{ $customer->created_at?->format('M j, Y') }}</p>
            <h1 class="flex flex-wrap items-center gap-3 text-xl font-semibold text-stone-900 dark:text-stone-100">
                {{ $customer->displayName() }}
                <x-admin.status-badge :tint="$tint">{{ $standingLabel }}</x-admin.status-badge>
            </h1>
        </div>
        <a href="{{ route('admin.customers.index') }}" class="hidden text-stone-700 dark:text-stone-300 underline sm:inline">All customers</a>
    </div>

    <dl class="mt-6 grid grid-cols-1 gap-x-8 border-t border-stone-200 dark:border-stone-800 sm:grid-cols-2">
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-stone-800 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Email</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $customer->email ?? '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-stone-200 dark:border-stone-800 py-3">
            <dt class="font-medium text-stone-900 dark:text-stone-100">Joined</dt>
            <dd class="text-right text-stone-600 dark:text-stone-400">{{ $customer->created_at?->format('M j, Y') }}</dd>
        </div>
    </dl>

    <section aria-labelledby="standing-heading" class="mt-6 max-w-xl">
        <h2 id="standing-heading" class="font-semibold text-stone-700 dark:text-stone-300">Standing</h2>

        @if ($customer->activeBlock)
            <dl class="mt-2 rounded border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 p-4 text-red-900 dark:text-red-200">
                <dt class="font-semibold">Blocked</dt>
                <dd class="mt-1">{{ $customer->activeBlock->reason }}</dd>
                <dd class="mt-1 text-red-700 dark:text-red-400">Since {{ $customer->activeBlock->created_at?->format('M j, Y g:ia') }}</dd>
            </dl>

            <form method="POST" action="{{ route('admin.customers.blocks.lift', $customer) }}" class="mt-2">
                @csrf
                <button type="submit" class="block w-full rounded-md bg-stone-700 px-4 py-2 text-center font-semibold text-white hover:bg-stone-600 sm:inline-block sm:w-auto">Lift block</button>
            </form>
        @else
            <p class="mt-2 rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4 text-stone-600 dark:text-stone-400">Not blocked.</p>

            <form method="POST" action="{{ route('admin.customers.blocks.store', $customer) }}"
                  class="mt-2 rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
                @csrf

                <label for="reason" class="block font-medium text-stone-900 dark:text-stone-100">Reason</label>
                <input id="reason" name="reason" type="text" required maxlength="500" value="{{ old('reason') }}"
                       class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-stone-900 outline outline-1 -outline-offset-1 outline-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:outline-stone-600">
                @error('reason')
                    <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-4 block w-full rounded-md bg-stone-700 px-4 py-2 text-center font-semibold text-white hover:bg-stone-600 sm:inline-block sm:w-auto">Block</button>
            </form>
        @endif
    </section>

    <section aria-labelledby="block-history-heading" class="mt-6">
        <h2 id="block-history-heading" class="font-semibold text-stone-700 dark:text-stone-300">Block history</h2>

        @if ($customer->blocks->isEmpty())
            <x-admin.nothing>Never blocked.</x-admin.nothing>
        @else
            <div class="mt-2 hidden overflow-x-auto rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
                <table class="w-full text-left">
                    <caption class="sr-only">Every block this customer has been under</caption>
                    <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Reason</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Blocked</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Lifted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
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

            <x-admin.card-list class="mt-2" caption="Every block this customer has been under">
                @foreach ($customer->blocks as $block)
                    <x-admin.card-row>
                        <span class="font-medium">{{ $block->reason }}</span>
                        <div class="flex items-center justify-between gap-3 text-stone-600 dark:text-stone-400">
                            <span>{{ $block->created_at?->format('M j, Y g:ia') }}</span>
                            <span>{{ $block->lifted_at?->format('M j, Y g:ia') ?? 'Active' }}</span>
                        </div>
                    </x-admin.card-row>
                @endforeach
            </x-admin.card-list>
        @endif
    </section>

    <section aria-labelledby="merges-heading" class="mt-6">
        <h2 id="merges-heading" class="font-semibold text-stone-700 dark:text-stone-300">Merge history</h2>

        @if ($customer->mergesAsCustomer->isEmpty() && $customer->mergesAsAnonymous->isEmpty())
            <x-admin.nothing>No merges.</x-admin.nothing>
        @else
            <div class="mt-2 hidden overflow-x-auto rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 sm:block">
                <table class="w-full text-left">
                    <caption class="sr-only">Anonymous visitors folded into this account, and the account this row was folded into</caption>
                    <thead class="border-b border-stone-300 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Direction</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Other customer</th>
                            <th scope="col" class="px-4 py-2 font-semibold">Merged</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
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

            <x-admin.card-list class="mt-2" caption="Anonymous visitors folded into this account, and the account this row was folded into">
                @foreach ($customer->mergesAsCustomer as $merge)
                    <x-admin.card-row>
                        <span class="font-medium">Absorbed</span>
                        <div class="flex items-center justify-between gap-3 text-stone-600 dark:text-stone-400">
                            <a href="{{ route('admin.customers.show', $merge->anonymous_customer_id) }}" class="underline">{{ $merge->anonymous_customer_id }}</a>
                            <span>{{ $merge->created_at?->format('M j, Y g:ia') }}</span>
                        </div>
                    </x-admin.card-row>
                @endforeach
                @foreach ($customer->mergesAsAnonymous as $merge)
                    <x-admin.card-row>
                        <span class="font-medium">Folded into</span>
                        <div class="flex items-center justify-between gap-3 text-stone-600 dark:text-stone-400">
                            <a href="{{ route('admin.customers.show', $merge->customer_id) }}" class="underline">{{ $merge->customer_id }}</a>
                            <span>{{ $merge->created_at?->format('M j, Y g:ia') }}</span>
                        </div>
                    </x-admin.card-row>
                @endforeach
            </x-admin.card-list>
        @endif
    </section>

    <section aria-labelledby="orders-heading" class="mt-6">
        <h2 id="orders-heading" class="font-semibold text-stone-700 dark:text-stone-300">Orders</h2>

        <x-admin.orders-table :orders="$customer->orders" :show-customer="false" caption="Orders this customer placed" />
    </section>

    <section aria-labelledby="favorites-heading" class="mt-6">
        <h2 id="favorites-heading" class="font-semibold text-stone-700 dark:text-stone-300">Favorites</h2>

        @if ($customer->favorites->isEmpty())
            <x-admin.nothing>No favorites.</x-admin.nothing>
        @else
            <ul class="mt-2 divide-y divide-stone-200 dark:divide-stone-800 rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
                @foreach ($customer->favorites as $favorite)
                    <li class="px-4 py-2">
                        <a href="{{ route('admin.listings.show', $favorite->listing) }}" class="underline">{{ $favorite->listing->title }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="cart-heading" class="mt-6">
        <h2 id="cart-heading" class="font-semibold text-stone-700 dark:text-stone-300">Cart</h2>

        @if ($customer->cartItems->isEmpty())
            <x-admin.nothing>Cart is empty.</x-admin.nothing>
        @else
            <ul class="mt-2 divide-y divide-stone-200 dark:divide-stone-800 rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900">
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
        <h2 id="message-heading" class="font-semibold text-stone-700 dark:text-stone-300">Message customer</h2>

        <x-messaging.body-form
            :action="route('admin.customers.messages', $customer)"
            label="Message"
            class="mt-2"
        />
    </section>
</x-layouts.admin>
