<x-layouts.shop title="Orders — Art Store">
    <h1 class="font-display text-4xl leading-tight text-ink">Orders</h1>

    @if ($orders->isEmpty())
        <p class="mt-10 text-lg text-ink-muted">No orders yet.</p>
    @else
        <ul class="mt-12 divide-y divide-line border-y border-line">
            @foreach ($orders as $order)
                <li class="flex flex-wrap items-baseline justify-between gap-6 py-6">
                    <div>
                        <a href="{{ route('shop.order', $order) }}" class="text-lg font-medium text-ink">Order {{ $order->id }}</a>
                        <p class="mt-1 text-base text-ink-muted">
                            {{ $order->items->pluck('title')->join(', ') }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-base text-ink-faint">{{ $order->placed_at->format('j M Y') }}</p>
                        <p class="mt-1 text-lg text-ink">{{ $order->total() }}</p>
                        <p class="mt-1 text-base text-ink-muted">{{ $order->status->label() }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-8">{{ $orders->links() }}</div>
    @endif
</x-layouts.shop>
