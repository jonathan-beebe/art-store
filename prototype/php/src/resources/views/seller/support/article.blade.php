<x-layouts.seller :title="$article->title.' — Art Store seller'">
    <a href="{{ route('seller.support') }}" class="inline-flex items-center gap-1.5 text-sm/6 font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 4L6 8l4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
        Support
    </a>

    <article class="mt-3 max-w-2xl rounded-lg border border-gray-200 bg-white p-8 dark:border-white/10 dark:bg-gray-900">
        <p class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $article->group }}</p>
        <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $article->title }}</h1>

        <div class="mt-4 flex flex-col gap-3 text-gray-700 dark:text-gray-300">
            @foreach ($article->paragraphs as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>

        <div class="mt-6 flex items-center gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
            <span class="text-gray-500 dark:text-gray-400">Did this answer it?</span>
            <a href="{{ route('seller.support') }}" class="rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">Yes</a>
            <a href="{{ route('seller.support.create') }}" class="rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20">No, ask the desk</a>
        </div>
    </article>
</x-layouts.seller>
