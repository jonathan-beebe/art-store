@csrf

<div>
    <label for="title" class="block font-medium text-gray-700">Title</label>
    <input id="title" name="title" type="text" required maxlength="255"
           value="{{ old('title', $listing?->title) }}"
           aria-describedby="@error('title') title-error @enderror"
           class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
    @error('title')
        <p id="title-error" class="mt-1 text-red-700">{{ $message }}</p>
    @enderror
</div>

<div class="mt-4">
    <label for="description" class="block font-medium text-gray-700">Description</label>
    <textarea id="description" name="description" rows="4" maxlength="5000"
              aria-describedby="@error('description') description-error @enderror"
              class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">{{ old('description', $listing?->description) }}</textarea>
    @error('description')
        <p id="description-error" class="mt-1 text-red-700">{{ $message }}</p>
    @enderror
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <div>
        <label for="medium" class="block font-medium text-gray-700">Medium</label>
        <input id="medium" name="medium" type="text" maxlength="255"
               value="{{ old('medium', $listing?->medium) }}"
               aria-describedby="@error('medium') medium-error @enderror"
               class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
        @error('medium')
            <p id="medium-error" class="mt-1 text-red-700">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="dimensions" class="block font-medium text-gray-700">Dimensions</label>
        <input id="dimensions" name="dimensions" type="text" maxlength="255"
               value="{{ old('dimensions', $listing?->dimensions) }}"
               aria-describedby="@error('dimensions') dimensions-error @enderror"
               class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
        @error('dimensions')
            <p id="dimensions-error" class="mt-1 text-red-700">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="price" class="block font-medium text-gray-700">Price (dollars)</label>
        <input id="price" name="price" type="number" step="0.01" min="0" required
               value="{{ old('price', $listing === null ? null : number_format($listing->price_cents / 100, 2, '.', '')) }}"
               aria-describedby="@error('price') price-error @enderror"
               class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
        @error('price')
            <p id="price-error" class="mt-1 text-red-700">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="quantity" class="block font-medium text-gray-700">Quantity</label>
        <input id="quantity" name="quantity" type="number" step="1" min="0" max="999" required
               value="{{ old('quantity', $listing?->quantity ?? 1) }}"
               aria-describedby="@error('quantity') quantity-error @enderror"
               class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
        @error('quantity')
            <p id="quantity-error" class="mt-1 text-red-700">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-4">
    <label for="image" class="block font-medium text-gray-700">Image</label>
    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
           aria-describedby="image-hint @error('image') image-error @enderror"
           class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">
    <p id="image-hint" class="mt-1 text-gray-600">JPEG, PNG, WebP, or GIF up to 5 MB. A placeholder is drawn from the title when none is uploaded.</p>
    @error('image')
        <p id="image-error" class="mt-1 text-red-700">{{ $message }}</p>
    @enderror
</div>
