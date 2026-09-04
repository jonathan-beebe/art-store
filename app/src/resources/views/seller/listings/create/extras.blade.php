@php
    $rows = old('extra_options', [[], []]);
@endphp

<x-layouts.seller :title="$title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.create') }}" class="text-gray-700 dark:text-gray-300 underline">&larr; New listing</a></p>
    <h1 class="mt-2 text-xl font-semibold">{{ $title }}</h1>
    <p class="mt-1 max-w-lg text-gray-600 dark:text-gray-400">One price, with extras. Set the item&rsquo;s own price, then name the first extra and what each option adds.</p>

    <form method="POST" action="{{ route('seller.listings.store') }}"
          class="mt-4 max-w-lg rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf
        <input type="hidden" name="shape" value="extras">
        <input type="hidden" name="title" value="{{ $title }}">

        <div class="grid grid-cols-2 gap-4">
            <x-form.field name="price" label="The item’s price" type="number" step="0.01" min="0" required :value="old('price')" />

            <div>
                <x-form.field name="quantity" label="How many you have" type="number" step="1" min="0" max="999" :value="old('quantity')" />
                <label class="mt-1 flex items-center gap-2 text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="made_to_order" value="1" @checked(old('made_to_order'))>
                    Made to order — no fixed count
                </label>
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">With &ldquo;Made to order&rdquo; checked, the count is ignored and the listing stays available.</p>

        <div class="mt-4 border-t border-gray-100 dark:border-gray-800 pt-4">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <x-form.field name="extra_choice_name" label="The first extra buyers choose" maxlength="255"
                                  placeholder="Finish" :value="old('extra_choice_name')" />
                </div>
                <span class="mb-1 whitespace-nowrap rounded-full border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 px-3 py-0.5 text-xs text-gray-700 dark:text-gray-300">adds to your price</span>
            </div>

            <div class="mt-3 flex flex-col divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($rows as $index => $row)
                    <div class="flex items-start gap-3 py-2">
                        <div class="flex-1">
                            <label for="extra-label-{{ $index }}" class="sr-only">Option {{ $index + 1 }} name</label>
                            <input id="extra-label-{{ $index }}" name="extra_options[{{ $index }}][label]" type="text"
                                   value="{{ old("extra_options.$index.label") }}" placeholder="Another option"
                                   class="block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                            @error("extra_options.$index.label")
                                <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="w-32">
                            <label for="extra-price-{{ $index }}" class="sr-only">Option {{ $index + 1 }} price</label>
                            <input id="extra-price-{{ $index }}" name="extra_options[{{ $index }}][price]" type="text"
                                   value="{{ old("extra_options.$index.price") }}" placeholder="+$0.00"
                                   class="block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                            @error("extra_options.$index.price")
                                <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endforeach
            </div>
            @error('extra_choice_name')
                <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror
            @error('extra_options')
                <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
            @enderror

            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Skip this for now?
                <button type="submit" name="skip_extra" value="1" class="text-gray-700 dark:text-gray-300 underline">Create with just the price</button>
                — extras can come later.
            </p>
        </div>

        <div class="mt-4">
            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Create the draft</button>
        </div>
    </form>

    <p class="mt-4 max-w-lg text-sm text-gray-600 dark:text-gray-400">Lands on the editor with your extra in place, if you added one. More extras, buyer questions like an engraving name, and everything else wait there.</p>
</x-layouts.seller>
