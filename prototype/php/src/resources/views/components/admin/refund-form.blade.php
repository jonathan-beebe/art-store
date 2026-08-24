@props(['fulfillment'])

@if ($fulfillment->isRefundable())
    <form method="POST" action="{{ route('admin.fulfillments.refund', $fulfillment) }}"
          class="mt-2 rounded border border-gray-300 bg-white p-4">
        @csrf

        <p class="text-gray-600">
            Refunds {{ $fulfillment->subtotal()->format() }} to the customer. Stock stays sold: the pieces are
            already with them, or with a seller who is not answering for them.
        </p>

        <div class="mt-4">
            <label for="reason-{{ $fulfillment->id }}" class="block font-medium text-gray-700">Reason</label>
            <textarea id="reason-{{ $fulfillment->id }}" name="reason" required minlength="1" maxlength="500" rows="2"
                      class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">{{ old('reason') }}</textarea>
            @error('reason')
                <p class="mt-1 text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="mt-4 rounded border border-gray-400 bg-white px-4 py-2 font-medium">Refund this fulfillment</button>
    </form>
@else
    <x-admin.nothing>Nothing left to refund on this fulfillment.</x-admin.nothing>
@endif
