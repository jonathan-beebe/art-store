@props(['action', 'label' => 'Reply', 'submitLabel' => 'Send', 'class' => 'mt-6 max-w-xl rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4'])

<form method="POST" action="{{ $action }}" class="{{ $class }}">
    @csrf

    <label for="body" class="block font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    <textarea id="body" name="body" required rows="4" maxlength="2000"
              class="mt-1 block w-full rounded border border-gray-400 dark:border-gray-600 px-3 py-2">{{ old('body') }}</textarea>
    @error('body')
        <p class="mt-1 text-red-700 dark:text-red-400">{{ $message }}</p>
    @enderror

    <button type="submit" class="mt-4 block w-full rounded bg-gray-900 dark:bg-gray-100 px-4 py-2 text-center font-medium text-white dark:text-gray-900 sm:inline-block sm:w-auto">{{ $submitLabel }}</button>
</form>
