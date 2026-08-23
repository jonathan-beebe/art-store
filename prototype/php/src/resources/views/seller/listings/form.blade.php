@csrf

<x-form.field name="title" label="Title" required maxlength="255" :value="$listing?->title" />

<x-form.field name="description" label="Description" type="textarea" class="mt-4" rows="4" maxlength="5000"
              :value="$listing?->description" />

<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <x-form.field name="medium" label="Medium" maxlength="255" :value="$listing?->medium" />

    <x-form.field name="dimensions" label="Dimensions" maxlength="255" :value="$listing?->dimensions" />

    <x-form.field name="price" label="Price (dollars)" type="number" step="0.01" min="0" required
                  :value="$listing === null ? null : number_format($listing->price_cents / 100, 2, '.', '')" />

    <x-form.field name="quantity" label="Quantity" type="number" step="1" min="0" max="999" required
                  :value="$listing?->quantity ?? 1" />
</div>

<x-form.field name="image" label="Image" type="file" class="mt-4" accept="image/jpeg,image/png,image/webp,image/gif"
              hint="JPEG, PNG, WebP, or GIF up to 5 MB. A placeholder is drawn from the title when none is uploaded." />
