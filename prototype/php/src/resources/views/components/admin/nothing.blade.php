{{-- The panel every admin table falls back to when its query found no rows. --}}
<p {{ $attributes->merge(['class' => 'mt-2 rounded border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 text-gray-600 dark:text-gray-400']) }}>{{ $slot }}</p>
