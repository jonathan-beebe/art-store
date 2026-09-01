{{-- The panel every admin table falls back to when its query found no rows. --}}
<p {{ $attributes->merge(['class' => 'mt-2 rounded border border-stone-300 dark:border-stone-700 bg-white dark:bg-stone-900 p-4 text-stone-600 dark:text-stone-400']) }}>{{ $slot }}</p>
