@if (session('debug_magic_link'))
    <div role="alert" class="border-b border-yellow-300 dark:border-yellow-900 bg-yellow-100 dark:bg-yellow-950 px-4 py-3 text-sm text-yellow-900 dark:text-yellow-200">
        <span class="font-semibold">Debug magic link:</span>
        <a href="{{ session('debug_magic_link') }}" class="break-all underline">{{ session('debug_magic_link') }}</a>
    </div>
@elseif (session('debug_notice'))
    <div role="alert" class="border-b border-yellow-300 dark:border-yellow-900 bg-yellow-100 dark:bg-yellow-950 px-4 py-3 text-sm text-yellow-900 dark:text-yellow-200">
        <span class="font-semibold">Debug notice:</span>
        {{ session('debug_notice') }}
    </div>
@endif
