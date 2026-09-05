<x-layouts.seller title="New conversation — Art Store seller">
    <x-seller.back-link :route="route('seller.support')" label="Support" />

    <div class="mt-2 max-w-xl">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">New conversation with Art Store Support</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ask about payouts, a listing, an order, or anything about selling here. Anna or Jonathan will answer in the thread.</p>

        <form method="POST" action="{{ route('seller.support.store') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
                <input
                    id="title" name="title" type="text" required maxlength="{{ \App\Domain\Messaging\ThreadTitle::MAX_LENGTH }}"
                    value="{{ old('title') }}"
                    class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10"
                >
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">A short line that says what the conversation is about. {{ \App\Domain\Messaging\ThreadTitle::MAX_LENGTH }} characters.</p>
                @error('title')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="fulfillment_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    About an order <span class="font-normal text-gray-500 dark:text-gray-500">(optional)</span>
                </label>
                <select
                    id="fulfillment_id" name="fulfillment_id"
                    class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10"
                >
                    <option value="">None</option>
                    @foreach ($fulfillments as $fulfillment)
                        <option value="{{ $fulfillment->id }}" @selected(old('fulfillment_id') === $fulfillment->id)>
                            {{ $fulfillment->id }} &middot; {{ $fulfillment->order->shipping_name }} &middot; {{ $fulfillment->status->label() }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">The desk sees the order beside the thread.</p>
                @error('fulfillment_id')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Message</label>
                <div class="mt-1 overflow-hidden rounded-lg bg-white outline-1 -outline-offset-1 outline-gray-300 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-indigo-600 dark:bg-white/5 dark:outline-white/10 dark:focus-within:outline-indigo-500">
                    <textarea
                        id="body" name="body" required rows="5"
                        maxlength="{{ \App\Domain\Messaging\MessageBody::MAX_LENGTH }}"
                        data-composer
                        class="field-sizing-content block max-h-72 w-full resize-none bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none dark:text-white dark:placeholder:text-gray-500"
                    >{{ old('body') }}</textarea>
                    <div class="flex items-center gap-3 border-t border-gray-100 px-3 py-2 dark:border-white/10">
                        <span data-composer-count class="text-xs text-gray-500 dark:text-gray-400">{{ number_format(mb_strlen(old('body', ''))) }} / {{ number_format(\App\Domain\Messaging\MessageBody::MAX_LENGTH) }}</span>
                        <span class="ml-auto text-xs text-gray-500 dark:text-gray-400"><kbd class="rounded border border-gray-300 px-1 font-mono dark:border-white/20">&#8984;</kbd> <kbd class="rounded border border-gray-300 px-1 font-mono dark:border-white/20">Enter</kbd> to send</span>
                    </div>
                </div>
                @error('body')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Start conversation</button>
                <a href="{{ route('seller.support') }}" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">Cancel</a>
            </div>
        </form>
    </div>
</x-layouts.seller>
