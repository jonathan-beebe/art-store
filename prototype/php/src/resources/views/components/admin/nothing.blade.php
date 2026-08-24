{{-- The panel every admin table falls back to when its query found no rows. --}}
<p {{ $attributes->merge(['class' => 'mt-2 rounded border border-gray-300 bg-white p-4 text-gray-600']) }}>{{ $slot }}</p>
