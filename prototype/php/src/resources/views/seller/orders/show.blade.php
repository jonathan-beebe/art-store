@extends('layouts.seller')
@use('App\Domain\Money\Money')
@use('App\Domain\Reports\StatusLabel')

@section('title', 'Order #'.$fulfillment->order_id.' — Art Store seller')

@section('content')
    <div class="flex flex-wrap items-center gap-4">
        <h1 class="text-xl font-semibold">Order #{{ $fulfillment->order_id }}</h1>
        <p class="text-gray-600">{{ StatusLabel::of($fulfillment->status) }}</p>
        <a href="{{ route('seller.orders.index') }}" class="ml-auto text-gray-700 underline">All orders</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="address-heading">
            <h2 id="address-heading" class="font-semibold text-gray-700">Ship to</h2>

            <address class="mt-2 rounded border border-gray-300 bg-white p-4 not-italic">
                {{ $fulfillment->order->shipping_name }}<br>
                {{ $fulfillment->order->shipping_line1 }}<br>
                @if ($fulfillment->order->shipping_line2)
                    {{ $fulfillment->order->shipping_line2 }}<br>
                @endif
                {{ $fulfillment->order->shipping_city }}, {{ $fulfillment->order->shipping_region }}<br>
                {{ $fulfillment->order->shipping_postal_code }}<br>
                {{ $fulfillment->order->shipping_country }}
            </address>
        </section>

        <section aria-labelledby="items-heading">
            <h2 id="items-heading" class="font-semibold text-gray-700">Your items</h2>

            <div class="mt-2 overflow-x-auto rounded border border-gray-300 bg-white">
                <table class="w-full text-left">
                    <caption class="sr-only">Items in this order that belong to you</caption>
                    <thead class="border-b border-gray-300 bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-semibold">Item</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Qty</th>
                            <th scope="col" class="px-4 py-2 text-right font-semibold">Unit price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($fulfillment->order->items as $item)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal">{{ $item->title }}</th>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $item->quantity }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ Money::fromCents($item->unit_price_cents)->format() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-300">
                        <tr>
                            <th scope="row" class="px-4 py-2 text-left font-semibold">Net to you</th>
                            <td colspan="2" class="px-4 py-2 text-right font-semibold tabular-nums">{{ $fulfillment->net()->format() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>

    <section aria-labelledby="shipment-heading" class="mt-6 max-w-xl">
        <h2 id="shipment-heading" class="font-semibold text-gray-700">Shipment</h2>

        @can('ship', $fulfillment)
            <form method="POST" action="{{ route('seller.orders.ship', $fulfillment->id) }}"
                  class="mt-2 rounded border border-gray-300 bg-white p-4">
                @csrf

                <fieldset>
                    <legend class="font-medium text-gray-700">Mark shipped</legend>

                    <div class="mt-2">
                        <label for="carrier" class="block font-medium text-gray-700">Carrier</label>
                        <input id="carrier" name="carrier" type="text" required maxlength="255" value="{{ old('carrier') }}"
                               class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
                        @error('carrier')
                            <p class="mt-1 text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <label for="tracking_number" class="block font-medium text-gray-700">Tracking number</label>
                        <input id="tracking_number" name="tracking_number" type="text" required maxlength="255" value="{{ old('tracking_number') }}"
                               class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
                        @error('tracking_number')
                            <p class="mt-1 text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                </fieldset>

                <button type="submit" class="mt-4 rounded bg-gray-900 px-4 py-2 font-medium text-white">Mark shipped</button>
            </form>
        @else
            <dl class="mt-2 rounded border border-gray-300 bg-white p-4">
                <dt class="text-gray-600">Carrier</dt>
                <dd class="mt-1">{{ $fulfillment->carrier }}</dd>
                <dt class="mt-3 text-gray-600">Tracking number</dt>
                <dd class="mt-1">{{ $fulfillment->tracking_number }}</dd>
                <dt class="mt-3 text-gray-600">Shipped</dt>
                <dd class="mt-1">{{ $fulfillment->shipped_at?->format('M j, Y g:ia') }}</dd>
                @if ($fulfillment->delivered_at)
                    <dt class="mt-3 text-gray-600">Delivered</dt>
                    <dd class="mt-1">{{ $fulfillment->delivered_at->format('M j, Y g:ia') }}</dd>
                @endif
            </dl>
        @endcan
    </section>
@endsection
