@php
    use App\Domain\Messaging\MessageBody;
    use App\Domain\Messaging\ThreadTitle;

    $selectedOrderId = old('order_id', $preselectedOrderId ?? '');
@endphp
<x-layouts.shop title="Talk to us — Art Store">
    <div class="grid gap-16 lg:grid-cols-[minmax(0,40rem)_minmax(0,1fr)]">
        <div>
            <h1 class="font-display text-4xl leading-tight text-ink">Talk to us</h1>
            <p class="mt-3 text-lg leading-relaxed text-ink-muted">Anna and Jonathan run Art Store and read every message themselves. An order that hasn't arrived, a question about a maker, a thought about the shop — write to us here and we'll answer in your messages.</p>

            <form method="POST" action="{{ route('shop.support.store') }}" class="mt-8 flex flex-col gap-6">
                @csrf

                <div>
                    <x-ui.label for="subject">What's this about?</x-ui.label>
                    <x-ui.input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="{{ ThreadTitle::MAX_LENGTH }}" class="mt-1.5" />
                    @error('subject')
                        <p class="mt-1.5 text-danger">{{ $message }}</p>
                    @enderror
                </div>

                @if ($orders->isNotEmpty())
                    <div>
                        <x-ui.label for="order_id">Order <span class="font-normal text-ink-faint">(optional)</span></x-ui.label>
                        <x-ui.select id="order_id" name="order_id" class="mt-1.5">
                            <option value="">Not about a specific order</option>
                            @foreach ($orders as $order)
                                <option value="{{ $order->id }}" @selected($selectedOrderId === $order->id)>
                                    {{ $order->id }} · {{ $order->items->first()?->title }} · {{ $order->placed_at?->format('M j') }}
                                </option>
                            @endforeach
                        </x-ui.select>
                        <p class="mt-1.5 text-sm text-ink-faint">We'll see the order beside your message.</p>
                    </div>
                @endif

                <div>
                    <x-ui.label for="body">Message</x-ui.label>
                    <div class="mt-1.5">
                        <x-shop.messaging.composer
                            name="body"
                            :value="old('body', '')"
                            :maxlength="MessageBody::MAX_LENGTH"
                        />
                    </div>
                    @error('body')
                        <p class="mt-1.5 text-danger">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <x-ui.button variant="primary">Send to Art Store</x-ui.button>
                </div>
            </form>
        </div>

        <aside class="flex flex-col gap-5 pt-2 lg:pt-16">
            <div class="rounded-card border border-line bg-surface p-6">
                <p class="font-semibold text-ink">About an order from a maker?</p>
                <p class="mt-1.5 leading-relaxed text-ink-muted">Every order has its own conversation with the maker who made it. Find it on the order page — they'll usually know first.</p>
                <a href="{{ route('shop.orders') }}" class="mt-2.5 inline-block font-semibold text-accent hover:underline">Your orders →</a>
            </div>

            <div class="rounded-card border border-line bg-surface p-6">
                <p class="font-semibold text-ink">Your open conversations with us</p>
                @if ($openConversations->isEmpty())
                    <p class="mt-1.5 leading-relaxed text-ink-muted">None yet. Each message you send here starts one, and stays in Messages.</p>
                @else
                    <ul class="mt-3 flex flex-col gap-2.5">
                        @foreach ($openConversations as $conversation)
                            <li>
                                <a href="{{ route('shop.messages.show', $conversation) }}" class="font-medium text-accent hover:underline">{{ $conversation->title }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>
</x-layouts.shop>
