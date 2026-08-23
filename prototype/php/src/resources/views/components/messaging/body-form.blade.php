@props(['action', 'label' => 'Reply', 'submitLabel' => 'Send', 'class' => 'mt-6 max-w-xl rounded border border-gray-300 bg-white p-4'])

<form method="POST" action="{{ $action }}" class="{{ $class }}">
    @csrf

    <label for="body" class="block font-medium text-gray-700">{{ $label }}</label>
    <textarea id="body" name="body" required rows="4" maxlength="2000"
              class="mt-1 block w-full rounded border border-gray-400 px-3 py-2">{{ old('body') }}</textarea>
    @error('body')
        <p class="mt-1 text-red-700">{{ $message }}</p>
    @enderror

    <button type="submit" class="mt-4 rounded bg-gray-900 px-4 py-2 font-medium text-white">{{ $submitLabel }}</button>
</form>
