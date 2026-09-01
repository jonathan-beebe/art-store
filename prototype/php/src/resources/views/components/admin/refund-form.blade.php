@props(['fulfillment'])

@if ($fulfillment->isRefundable())
    <form method="POST" action="{{ route('admin.fulfillments.refund', $fulfillment) }}"
          class="mt-2 rounded-md border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4">
        @csrf

        <p class="text-stone-600 dark:text-stone-400">
            Refunds {{ $fulfillment->subtotal()->format() }} to the customer. Stock stays sold: the pieces are
            already with them, or with a seller who is not answering for them.
        </p>

        <div class="mt-4">
            <label for="reason-{{ $fulfillment->id }}" class="block font-medium text-stone-900 dark:text-stone-100">Reason</label>
            <textarea id="reason-{{ $fulfillment->id }}" name="reason" required minlength="1" maxlength="500" rows="2"
                      class="mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-stone-900 inset-ring inset-ring-stone-300 focus:outline-2 focus:-outline-offset-2 focus:outline-stone-600 dark:bg-white/5 dark:text-stone-100 dark:inset-ring-white/10">{{ old('reason') }}</textarea>
            @error('reason')
                <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="mt-4 block w-full rounded-md bg-stone-700 px-4 py-2 text-center font-medium text-white shadow-xs hover:bg-stone-600 sm:inline-block sm:w-auto">Refund this fulfillment</button>
    </form>
@else
    <x-admin.nothing>Nothing left to refund on this fulfillment.</x-admin.nothing>
@endif
