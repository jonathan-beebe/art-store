<div class="mt-4">
    <label for="card_number" class="block text-base font-medium">Card number</label>
    <input id="card_number" name="card_number" type="text" inputmode="numeric" autocomplete="cc-number"
           value="{{ old('card_number') }}" required placeholder="4242 4242 4242 4242"
           class="mt-2 block w-full rounded-field border border-line-strong px-4 py-3 text-lg">
    @error('card_number')
        <p class="mt-2 text-danger">{{ $message }}</p>
    @enderror
</div>

<div class="mt-4 flex gap-4">
    <div class="flex-1">
        <label for="card_expiry" class="block text-base font-medium">Expiry</label>
        <input id="card_expiry" name="card_expiry" type="text" autocomplete="cc-exp" placeholder="04 / 30"
               class="mt-2 block w-full rounded-field border border-line-strong px-4 py-3 text-lg">
    </div>

    <div class="flex-1">
        <label for="card_cvc" class="block text-base font-medium">CVC</label>
        <input id="card_cvc" name="card_cvc" type="text" autocomplete="cc-csc" placeholder="123"
               class="mt-2 block w-full rounded-field border border-line-strong px-4 py-3 text-lg">
    </div>
</div>
