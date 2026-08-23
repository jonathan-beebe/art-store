@if (session('debug_magic_link'))
    <div role="alert" class="border-b border-yellow-300 bg-yellow-100 px-4 py-3 text-sm text-yellow-900">
        <span class="font-semibold">Debug magic link:</span>
        <a href="{{ session('debug_magic_link') }}" class="break-all underline">{{ session('debug_magic_link') }}</a>
    </div>
@endif
