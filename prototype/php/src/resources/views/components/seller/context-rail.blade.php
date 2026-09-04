{{--
    Who the seller is talking to, beside the words: the counterpart, what
    they have bought here, the piece or the parcel the thread is about, and
    their other threads. Takes an `App\Seller\ThreadContext`.
--}}
@props(['context'])

<div class="flex items-center gap-3">
    <span aria-hidden="true" class="flex size-12 flex-none items-center justify-center rounded-full bg-indigo-50 text-base font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $context->initials }}</span>
    <div class="min-w-0">
        <p class="truncate font-semibold text-gray-900 dark:text-white">{{ $context->name }}</p>
        <p class="truncate text-xs/5 text-gray-500 dark:text-gray-400">{{ $context->isDesk ? 'Support desk' : ($context->email ?? 'No email') }}</p>
    </div>
</div>

@if ($context->customer)
    <dl class="mt-4 divide-y divide-gray-100 text-xs/5 dark:divide-white/5">
        <div class="flex justify-between gap-2 py-1.5">
            <dt class="text-gray-500 dark:text-gray-400">Orders</dt>
            <dd class="font-medium text-gray-900 tabular-nums dark:text-gray-100">{{ $context->customer->orders }} &middot; {{ $context->customer->spent()->format() }}</dd>
        </div>
        <div class="flex justify-between gap-2 py-1.5">
            <dt class="text-gray-500 dark:text-gray-400">Favorites</dt>
            <dd class="font-medium text-gray-900 tabular-nums dark:text-gray-100">{{ $context->customer->favorites }}</dd>
        </div>
        <div class="flex justify-between gap-2 py-1.5">
            <dt class="text-gray-500 dark:text-gray-400">Conversations</dt>
            <dd class="font-medium text-gray-900 tabular-nums dark:text-gray-100">{{ $context->customer->conversations }}</dd>
        </div>
        <div class="flex justify-between gap-2 py-1.5">
            <dt class="text-gray-500 dark:text-gray-400">Since</dt>
            <dd class="font-medium text-gray-900 tabular-nums dark:text-gray-100">{{ $context->customer->firstOrderAt->format('M j, Y') }}</dd>
        </div>
    </dl>

    <a href="{{ $context->customerHref() }}" class="mt-3 inline-block rounded text-xs font-semibold text-indigo-600 hover:text-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-indigo-400 dark:hover:text-indigo-300">View customer</a>
@endif

@if ($context->listing)
    <p class="mt-6 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">About this piece</p>
    <a href="{{ route('seller.listings.show', $context->listing) }}" class="mt-2 flex items-center gap-3 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
        <img src="{{ $context->listing->imageUrl() }}" alt="" class="size-12 flex-none rounded-md object-cover">
        <span class="min-w-0">
            <span class="block truncate font-semibold text-gray-900 dark:text-gray-100">{{ $context->listing->title }}</span>
            <span class="block text-xs/5 text-gray-500 dark:text-gray-400">{{ $context->listing->price()->format() }} &middot; {{ \App\Domain\Listings\ListingStockLabel::withInStock($context->listing->quantity) }}</span>
        </span>
    </a>
@endif

@if ($context->order)
    <p class="mt-6 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">About this order</p>
    <a href="{{ route('seller.orders.show', $context->order) }}" class="mt-2 block rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
        <span class="flex items-start justify-between gap-2">
            <span class="min-w-0 truncate font-semibold text-gray-900 dark:text-gray-100">{{ $context->order->itemLabel() }}</span>
            <x-seller.status-badge :tint="$context->order->status->sellerBadgeTint()">{{ $context->order->status->label() }}</x-seller.status-badge>
        </span>
        <span class="mt-0.5 block text-xs/5 text-gray-500 dark:text-gray-400">{{ $context->order->order_id }} &middot; {{ $context->order->subtotal()->format() }} &middot; placed {{ $context->order->order->placed_at?->format('M j, Y') }}</span>
    </a>
@endif

@if ($context->others->isNotEmpty())
    <p class="mt-6 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Other conversations</p>
    <ul role="list" class="mt-2 flex flex-col gap-2">
        @foreach ($context->others as $other)
            <li class="text-xs/5">
                <a href="{{ route('seller.messages.show', $other) }}" class="rounded font-medium text-gray-900 hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400">{{ $other->title ?? $other->kind->topic($other->fulfillment?->order_id, $other->listing?->title) }}</a>
                <span class="text-gray-500 dark:text-gray-400">@if ($other->last_message_at) &middot; {{ \App\Support\RelativeTime::short($other->last_message_at, now()) }} @endif</span>
            </li>
        @endforeach
    </ul>
@endif
