<x-layouts.shop title="Orders — Art Store">
    <h1 class="text-4xl font-semibold tracking-tight">Orders</h1>

    @if ($orders->isEmpty())
        <p class="mt-10 text-lg text-neutral-600">No orders yet.</p>
    @else
        <ul class="mt-12 divide-y divide-neutral-100 border-y border-neutral-100">
            @foreach ($orders as $order)
                <li class="flex flex-wrap items-baseline justify-between gap-6 py-6">
                    <div>
                        <a href="{{ route('shop.order', $order) }}" class="text-lg font-medium">Order {{ $order->id }}</a>
                        <p class="mt-1 text-base text-neutral-600">
                            {{ $order->items->pluck('title')->join(', ') }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-base text-neutral-500">{{ $order->placed_at->format('j M Y') }}</p>
                        <p class="mt-1 text-lg">{{ $order->total() }}</p>
                        <p class="mt-1 text-base text-neutral-600">{{ $order->status->label() }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.shop>
