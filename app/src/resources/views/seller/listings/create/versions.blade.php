@php
    $rows = old('versions', [[], [], []]);
@endphp

<x-layouts.seller :title="$title.' — Art Store seller'">
    <p><a href="{{ route('seller.listings.create') }}" class="text-gray-700 dark:text-gray-300 underline">&larr; New listing</a></p>
    <h1 class="mt-2 text-xl font-semibold">{{ $title }}</h1>
    <p class="mt-1 max-w-lg text-gray-600 dark:text-gray-400">Versions, each with its own price. Name the choice buyers make, then price every version — there&rsquo;s no separate &ldquo;base price&rdquo; to set, because each version&rsquo;s price is the price.</p>

    <form method="POST" action="{{ route('seller.listings.store') }}"
          class="mt-4 max-w-lg rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
        @csrf
        <input type="hidden" name="shape" value="versions">
        <input type="hidden" name="title" value="{{ $title }}">

        <div class="flex items-end gap-3">
            <div class="flex-1">
                <x-form.field name="choice_name" label="What do buyers choose between?" required maxlength="255"
                              placeholder="Size" :value="old('choice_name')" />
            </div>
            <span class="mb-1 whitespace-nowrap rounded-full bg-gray-900 dark:bg-gray-100 px-3 py-0.5 text-xs font-medium text-white dark:text-gray-900">each option priced on its own</span>
        </div>

        <div class="mt-4 flex flex-col divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($rows as $index => $row)
                <div class="flex items-start gap-3 py-2">
                    <div class="flex-1">
                        <label for="version-label-{{ $index }}" class="sr-only">Version {{ $index + 1 }} name</label>
                        <input id="version-label-{{ $index }}" name="versions[{{ $index }}][label]" type="text"
                               value="{{ old("versions.$index.label") }}" placeholder="Another version"
                               class="block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        @error("versions.$index.label")
                            <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="w-32">
                        <label for="version-price-{{ $index }}" class="sr-only">Version {{ $index + 1 }} price</label>
                        <input id="version-price-{{ $index }}" name="versions[{{ $index }}][price]" type="text"
                               value="{{ old("versions.$index.price") }}" placeholder="$0.00"
                               class="block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">
                        @error("versions.$index.price")
                            <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endforeach
        </div>
        @error('versions')
            <p class="mt-2 text-sm text-red-700 dark:text-red-400">{{ $message }}</p>
        @enderror

        <div class="mt-4">
            <button type="submit" class="rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 font-medium text-white dark:text-gray-900">Create the draft</button>
        </div>
    </form>

    <p class="mt-4 max-w-lg text-sm text-gray-600 dark:text-gray-400">Lands on the editor with your choice in place — stock per version, images, and everything else wait there. A framing extra or an engraving question can be added any time; versions and extras combine on one listing.</p>
</x-layouts.seller>
