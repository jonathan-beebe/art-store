@if (session('debug_magic_link'))
    <div role="alert" class="border-b border-notice-line bg-notice-surface px-4 py-3 text-sm text-notice">
        <span class="font-semibold">Debug magic link:</span>
        <a href="{{ session('debug_magic_link') }}" class="break-all underline">{{ session('debug_magic_link') }}</a>
    </div>
@elseif (session('debug_notice'))
    <div role="alert" class="border-b border-notice-line bg-notice-surface px-4 py-3 text-sm text-notice">
        <span class="font-semibold">Debug notice:</span>
        {{ session('debug_notice') }}
    </div>
@endif
