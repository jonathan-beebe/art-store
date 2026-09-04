<x-layouts.seller :title="'Order '.$fulfillment->order_id.' — Art Store seller'" :bleed="true">
    @php
        $tint = $fulfillment->status->sellerBadgeTint();
        $seller = auth('seller')->user();
        $canShip = $seller->can('ship', $fulfillment);
        $canDecline = $seller->can('decline', $fulfillment);
        $payment = $fulfillment->order->latestPayment;
        $shipTo = collect([
            $fulfillment->order->shipping_line1,
            $fulfillment->order->shipping_line2,
            trim($fulfillment->order->shipping_city.', '.$fulfillment->order->shipping_region.' '.$fulfillment->order->shipping_postal_code),
            $fulfillment->order->shipping_country,
        ])->filter()->implode(', ');
    @endphp

    <div class="h-[calc(100dvh-4rem)] overflow-hidden">
        <x-seller.list-detail mobile="detail">
            <x-slot:listHeader>
                <div class="flex items-baseline gap-2">
                    <h1 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Orders</h1>
                    @if ($needsActionCount > 0)
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $needsActionCount }} need action</span>
                    @endif
                </div>
            </x-slot:listHeader>

            <x-slot:list>
                <x-seller.fulfillment-cells :fulfillments="$cellFulfillments" :selected="$fulfillment" />
            </x-slot:list>

            <div class="flex h-full flex-col">
                <div class="min-h-0 flex-1 overflow-y-auto p-6">
                    <x-seller.back-link :route="route('seller.orders.index')" label="Orders" />

                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $fulfillment->order_id }} · placed {{ $fulfillment->order->placed_at?->format('M j, Y') }}</p>
                            <h2 class="mt-1 flex flex-wrap items-center gap-3 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $fulfillment->order->shipping_name }}
                                <x-seller.status-badge :tint="$tint">{{ $fulfillment->status->label() }}</x-seller.status-badge>
                            </h2>
                        </div>

                        @if ($canShip || $canDecline)
                            <div class="hidden shrink-0 items-center gap-3 lg:flex">
                                @if ($canDecline)
                                    <button type="submit" form="decline-form" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">Decline</button>
                                @endif
                                @if ($canShip)
                                    <button type="submit" form="mark-shipped-form" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Mark shipped</button>
                                @endif
                            </div>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('seller.orders.messages', $fulfillment) }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-sm text-gray-600 dark:text-gray-400 underline">Message the customer</button>
                    </form>

                    <dl class="mt-6 grid grid-cols-1 gap-x-8 border-t border-gray-200 dark:border-white/10 sm:grid-cols-2">
                        <div class="flex justify-between gap-4 border-b border-gray-200 dark:border-white/10 py-3">
                            <dt class="text-sm font-medium text-gray-900 dark:text-gray-100">Ships to</dt>
                            <dd class="text-right text-sm text-gray-600 dark:text-gray-400">{{ $shipTo }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-gray-200 dark:border-white/10 py-3">
                            <dt class="text-sm font-medium text-gray-900 dark:text-gray-100">Payment</dt>
                            <dd class="text-right text-sm text-gray-600 dark:text-gray-400">
                                @if ($payment)
                                    •••• {{ $payment->card_last_four }} — {{ $payment->status->label() }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-gray-200 dark:border-white/10 py-3">
                            <dt class="text-sm font-medium text-gray-900 dark:text-gray-100">Buyer email</dt>
                            <dd class="text-right text-sm text-gray-600 dark:text-gray-400">{{ $fulfillment->order->email ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-gray-200 dark:border-white/10 py-3">
                            <dt class="text-sm font-medium text-gray-900 dark:text-gray-100">Your take after fees</dt>
                            <dd class="text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $fulfillment->net()->format() }}</dd>
                        </div>
                    </dl>

                    <h3 class="mt-7 text-sm font-semibold text-gray-900 dark:text-gray-100">Items</h3>
                    <div class="mt-2 divide-y divide-gray-200 dark:divide-white/10 rounded-lg border border-gray-200 dark:border-white/10">
                        @foreach ($fulfillment->order->items as $item)
                            <div class="flex items-center gap-4 p-4">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-md bg-gray-50 text-gray-400 inset-ring inset-ring-gray-200 dark:bg-white/5 dark:text-gray-500 dark:inset-ring-white/10">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="size-5" aria-hidden="true"><path d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5V4.5A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21Z" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item->title }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $item->quantity }} × {{ $item->unitPrice()->format() }}</p>
                                    <x-order-item-detail :item="$item" />
                                </div>
                                <p class="shrink-0 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item->lineTotal()->format() }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($fulfillment->refund)
                        <section aria-labelledby="refund-heading" class="mt-7">
                            <h3 id="refund-heading" class="text-sm font-semibold text-gray-900 dark:text-gray-100">Refund</h3>
                            <dl class="mt-2 rounded-lg border border-gray-200 dark:border-white/10 p-4 text-sm">
                                <dt class="text-gray-500 dark:text-gray-400">Amount</dt>
                                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $fulfillment->refund->amount()->format() }}</dd>
                                <dt class="mt-3 text-gray-500 dark:text-gray-400">Reason</dt>
                                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $fulfillment->refund->reason }}</dd>
                                <dt class="mt-3 text-gray-500 dark:text-gray-400">Issued by</dt>
                                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $fulfillment->refund->issuerLabel() }}</dd>
                            </dl>
                        </section>
                    @endif

                    <div class="mt-7 flex flex-wrap items-baseline justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $flowName }}</h3>
                        <a href="{{ route('seller.orders.flow.edit') }}" class="text-sm/6 font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Flow settings</a>
                    </div>
                    <x-seller.flow-steps :fulfillment="$fulfillment" :steps="$flowSteps" :progress="$progress" :can-complete="$canShip" />

                    <h3 class="mt-7 text-sm font-semibold text-gray-900 dark:text-gray-100">Shipment</h3>
                    @if ($canShip)
                        <form id="mark-shipped-form" method="POST" action="{{ route('seller.orders.ship', $fulfillment->id) }}" class="mt-2 flex flex-col gap-4 rounded-lg border border-gray-200 dark:border-white/10 p-4 sm:flex-row sm:items-end">
                            @csrf

                            <div class="flex-1">
                                <label for="carrier" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Carrier</label>
                                <input id="carrier" name="carrier" type="text" required maxlength="255" value="{{ old('carrier') }}"
                                       class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-gray-100 dark:outline-white/10">
                                @error('carrier')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex-1">
                                <label for="tracking_number" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Tracking number</label>
                                <input id="tracking_number" name="tracking_number" type="text" required maxlength="255" value="{{ old('tracking_number') }}"
                                       class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-gray-100 dark:outline-white/10">
                                @error('tracking_number')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </form>
                    @else
                        <dl class="mt-2 rounded-lg border border-gray-200 dark:border-white/10 p-4 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">Carrier</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $fulfillment->carrier ?? '—' }}</dd>
                            <dt class="mt-3 text-gray-500 dark:text-gray-400">Tracking number</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $fulfillment->tracking_number ?? '—' }}</dd>
                            <dt class="mt-3 text-gray-500 dark:text-gray-400">Shipped</dt>
                            <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $fulfillment->shipped_at?->format('M j, Y g:ia') ?? '—' }}</dd>
                            @if ($fulfillment->delivered_at)
                                <dt class="mt-3 text-gray-500 dark:text-gray-400">Delivered</dt>
                                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $fulfillment->delivered_at->format('M j, Y g:ia') }}</dd>
                            @endif
                        </dl>
                    @endif

                    @if ($canDecline)
                        <h3 class="mt-7 text-sm font-semibold text-gray-900 dark:text-gray-100">Decline</h3>
                        <form id="decline-form" method="POST" action="{{ route('seller.orders.decline', $fulfillment->id) }}" class="mt-2 rounded-lg border border-gray-200 dark:border-white/10 p-4">
                            @csrf

                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Declining refunds {{ $fulfillment->subtotal()->format() }} to the customer and puts your pieces back on the storefront.
                            </p>

                            <div class="mt-4">
                                <label for="reason" class="block text-sm font-medium text-gray-900 dark:text-gray-100">Reason</label>
                                <textarea id="reason" name="reason" required minlength="1" maxlength="500" rows="3"
                                          class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-gray-100 dark:outline-white/10">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </form>
                    @endif
                </div>

                @if ($canShip || $canDecline)
                    <div class="flex shrink-0 gap-3 border-t border-gray-200 dark:border-white/10 p-4 lg:hidden">
                        @if ($canDecline)
                            <button type="submit" form="decline-form" class="min-h-11 flex-1 rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">Decline</button>
                        @endif
                        @if ($canShip)
                            <button type="submit" form="mark-shipped-form" class="min-h-11 flex-[2] rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400">Mark shipped</button>
                        @endif
                    </div>
                @endif
            </div>
        </x-seller.list-detail>
    </div>
</x-layouts.seller>
