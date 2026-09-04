@php
    use App\Domain\Store\StorePictureRole;
    use App\Domain\Store\StoreSectionField;
    use App\Domain\Store\StoreVisibility;
    use App\Http\Requests\Seller\StoreSectionRequest;

    $panel = 'rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900';
    $sectionRow = 'grid gap-8 p-6 lg:grid-cols-[220px_minmax(0,1fr)]';
    $label = 'block text-sm font-medium text-gray-700 dark:text-gray-300';
    $input = 'mt-1 block w-full rounded-md bg-white px-3 py-1.5 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600 dark:bg-white/5 dark:text-white dark:outline-white/10';
    $hint = 'mt-1 text-xs text-gray-500 dark:text-gray-500';
    $primary = 'rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-400';
    $secondary = 'rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20';
    $secondarySmall = 'rounded-md bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-900 shadow-xs inset-ring inset-ring-gray-300 hover:bg-gray-50 dark:bg-white/10 dark:text-white dark:shadow-none dark:inset-ring-white/10 dark:hover:bg-white/20';
@endphp

<x-layouts.seller title="Store — Art Store seller">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="flex items-center gap-3 text-xl font-semibold">
                Your store
                <x-seller.status-badge :tint="$page->profile->isPublished() ? 'green' : 'gray'">{{ $page->profile->visibility()->label() }}</x-seller.status-badge>
            </h1>
            <p class="mt-0.5 text-gray-500 dark:text-gray-400">How buyers meet you on the site: your name, your address, your story.</p>
        </div>

        <div class="flex gap-3">
            <button type="submit" form="store-form" class="{{ $primary }}">Save changes</button>
        </div>
    </div>

    <div class="mt-6 grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_384px]">
        <div class="{{ $panel }} divide-y divide-gray-200 dark:divide-white/10">
            <form id="store-form" method="POST" action="{{ route('seller.store.update') }}">
                @csrf
                @method('PUT')

                <section class="{{ $sectionRow }}">
                    <div>
                        <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Name and address</h2>
                        <p class="mt-1 text-xs/5 text-gray-500 dark:text-gray-400">The name is on every listing card. The address is the link you hand out.</p>
                    </div>
                    <div class="flex flex-col gap-5">
                        <div>
                            <label for="name" class="{{ $label }}">Store name</label>
                            <input id="name" name="name" type="text" required maxlength="255" value="{{ old('name', $page->profile->name) }}" class="{{ $input }}">
                            @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="slug" class="{{ $label }}">Store address</label>
                            <div class="mt-1 flex">
                                <span class="inline-flex items-center rounded-l-md bg-gray-50 px-3 py-1.5 text-sm text-gray-500 inset-ring inset-ring-gray-300 dark:bg-white/5 dark:text-gray-400 dark:inset-ring-white/10">{{ url('/s') }}/</span>
                                <input id="slug" name="slug" type="text" required value="{{ old('slug', $page->profile->slug) }}"
                                       class="{{ $input }} mt-0 -ml-px rounded-l-none">
                            </div>
                            <p class="{{ $hint }}">Lowercase letters, numbers, and hyphens. Changing it breaks links you have already shared; the old address forwards for 30 days.</p>
                            @error('slug')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="tagline" class="{{ $label }}">Tagline</label>
                            <input id="tagline" name="tagline" type="text" maxlength="80" value="{{ old('tagline', $page->profile->tagline) }}" class="{{ $input }}">
                            <p class="{{ $hint }}">One line under your name. 80 characters.</p>
                            @error('tagline')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="location" class="{{ $label }}">Where you make things</label>
                            <input id="location" name="location" type="text" maxlength="255" value="{{ old('location', $page->profile->location) }}" class="{{ $input }}">
                            <p class="{{ $hint }}">Town and region. Buyers see this; your street address stays private.</p>
                            @error('location')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                <section class="{{ $sectionRow }} border-t border-gray-200 dark:border-white/10">
                    <div>
                        <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Elsewhere</h2>
                        <p class="mt-1 text-xs/5 text-gray-500 dark:text-gray-400">Links shown under your story. Leave blank to hide.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach ($page->linkKinds as $kind)
                            <div>
                                <label for="link-{{ $kind->value }}" class="{{ $label }}">{{ $kind->label() }}</label>
                                <input id="link-{{ $kind->value }}" name="links[{{ $kind->value }}]" type="text" maxlength="255"
                                       placeholder="{{ $kind->placeholder() }}"
                                       value="{{ old('links.'.$kind->value, $page->linksByKind->get($kind->value)?->url) }}" class="{{ $input }}">
                                @error('links.'.$kind->value)<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="{{ $sectionRow }} border-t border-gray-200 dark:border-white/10">
                    <div>
                        <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Visibility</h2>
                        <p class="mt-1 text-xs/5 text-gray-500 dark:text-gray-400">A hidden store still sells. Its page cannot be opened and listing cards show only your name.</p>
                    </div>
                    <fieldset class="flex flex-col gap-3">
                        <legend class="sr-only">Visibility</legend>
                        @foreach (StoreVisibility::cases() as $visibility)
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="radio" name="visibility" value="{{ $visibility->value }}" class="mt-1 accent-indigo-600"
                                       @checked(old('visibility', $page->profile->visibility()->value) === $visibility->value)>
                                <span>
                                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $visibility->label() }}</span>
                                    <span class="text-xs/5 text-gray-500 dark:text-gray-400">
                                        {{ $visibility->isPublished()
                                            ? 'Anyone can open '.url('/s/'.$page->profile->slug).'.'
                                            : 'Keep working on it. Buyers cannot open the page.' }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                        @error('visibility')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </fieldset>
                </section>
            </form>

            <section class="{{ $sectionRow }} border-t border-gray-200 dark:border-white/10">
                <div>
                    <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Pictures</h2>
                    <p class="mt-1 text-xs/5 text-gray-500 dark:text-gray-400">A portrait for the avatar, a wide picture for the top of your page, and the ones your galleries place.</p>
                </div>
                <div class="flex flex-col gap-5">
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($page->profile->portraitImage)
                            <img src="{{ $page->profile->portraitImage->url() }}" alt="{{ $page->profile->portraitImage->alt ?? '' }}" class="size-16 rounded-full object-cover">
                        @endif
                        @if ($page->profile->coverImage)
                            <img src="{{ $page->profile->coverImage->url() }}" alt="{{ $page->profile->coverImage->alt ?? '' }}" class="h-16 w-48 rounded-md object-cover">
                        @endif
                    </div>

                    @if ($page->images->isNotEmpty())
                        <ul role="list" class="grid grid-cols-4 gap-3 sm:grid-cols-6">
                            @foreach ($page->images as $image)
                                <li data-store-picture class="flex flex-col gap-1">
                                    <img src="{{ $image->url() }}" alt="{{ $image->alt ?? '' }}" class="aspect-square w-full rounded-md object-cover">
                                    <form method="POST" action="{{ route('seller.store.images.destroy', $image) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="{{ $secondarySmall }} w-full">Remove<span class="sr-only"> {{ $image->alt ?: 'this picture' }}</span></button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($page->images->count() < $page->maxImages)
                        <form method="POST" action="{{ route('seller.store.images.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div>
                                <label for="image" class="{{ $label }}">Add a picture</label>
                                <input id="image" name="image" type="file" required accept="image/jpeg,image/png,image/webp,image/gif" class="{{ $input }}">
                            </div>
                            <div>
                                <label for="role" class="{{ $label }}">Use it as</label>
                                <select id="role" name="role" class="{{ $input }}">
                                    @foreach (StorePictureRole::cases() as $role)
                                        <option value="{{ $role->value }}" @selected($role === StorePictureRole::Gallery)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-56 flex-1">
                                <label for="alt" class="{{ $label }}">Describe it</label>
                                <input id="alt" name="alt" type="text" maxlength="255" value="{{ old('alt') }}"
                                       placeholder="The wheel by the window" class="{{ $input }}">
                            </div>
                            <button type="submit" class="{{ $secondary }}">Add</button>
                        </form>
                        <p class="{{ $hint }}">The description is what a screen reader reads in place of the picture.</p>
                        <p class="{{ $hint }}">JPEG, PNG, WebP, or GIF up to 5 MB. Up to {{ $page->maxImages }} pictures.</p>
                    @else
                        <p class="{{ $hint }}">This store already holds {{ $page->maxImages }} pictures, the most allowed.</p>
                    @endif

                    @error('image')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    @error('alt')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    @error('role')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="{{ $sectionRow }} border-t border-gray-200 dark:border-white/10">
                <div>
                    <h2 class="text-sm/6 font-semibold text-gray-900 dark:text-white">Your page</h2>
                    <p class="mt-1 text-xs/5 text-gray-500 dark:text-gray-400">Sections in the order buyers read them. A story is words; a gallery is pictures.</p>
                </div>
                <div class="flex flex-col gap-5">
                    @forelse ($page->profile->sections as $section)
                        @php
                            // A save that failed flashed its input and put
                            // its errors in this section's own bag. The
                            // section that failed shows what the seller
                            // typed; every other one shows what is stored.
                            $bag = $errors->getBag(StoreSectionRequest::errorBagFor($section));
                            $failed = $bag->any();
                            $typed = fn (string $field, ?string $stored): ?string => $failed ? old($field, $stored) : $stored;
                        @endphp

                        <form method="POST" action="{{ route('seller.store.sections.update', $section) }}"
                              class="rounded-md border border-gray-200 p-4 dark:border-white/10">
                            @csrf
                            @method('PUT')

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $section->kind->label() }}</h3>
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="{{ $secondarySmall }}">Save</button>
                                </div>
                            </div>

                            @if ($section->kind->allows(StoreSectionField::Heading))
                                <div class="mt-3">
                                    <label for="heading-{{ $section->id }}" class="{{ $label }}">Heading</label>
                                    <input id="heading-{{ $section->id }}" name="heading" type="text" maxlength="255"
                                           value="{{ $typed('heading', $section->heading) }}" class="{{ $input }}">
                                    @if ($bag->has('heading'))
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $bag->first('heading') }}</p>
                                    @endif
                                </div>
                            @endif

                            @if ($section->kind->allows(StoreSectionField::Body))
                                <div class="mt-3">
                                    <label for="body-{{ $section->id }}" class="{{ $label }}">Your story</label>
                                    {{-- No `maxlength`: the browser would truncate silently, and the
                                         request has the ceiling and a message for going past it. --}}
                                    <textarea id="body-{{ $section->id }}" name="body" rows="8"
                                              class="{{ $input }} resize-y">{{ $typed('body', $section->body) }}</textarea>
                                    <p class="{{ $hint }}">Who you are, how you work, why you make what you make. Up to {{ number_format($page->maxBodyLength) }} characters.</p>
                                    @if ($bag->has('body'))
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $bag->first('body') }}</p>
                                    @endif
                                </div>
                            @endif

                            @if ($section->kind->allows(StoreSectionField::Images))
                                @php
                                    // The place each picture already holds in
                                    // this gallery, so the number beside a
                                    // checked picture is the one the page
                                    // renders it at.
                                    $placedAt = $section->sectionImages
                                        ->pluck('position', 'store_image_id')
                                        ->all();
                                @endphp
                                <fieldset class="mt-3">
                                    <legend class="{{ $label }}">Pictures in this gallery</legend>
                                    <div class="mt-2 grid grid-cols-4 gap-3 sm:grid-cols-6">
                                        @foreach ($page->images as $image)
                                            @php($place = $placedAt[$image->id] ?? null)
                                            <div class="relative">
                                                <label class="block cursor-pointer">
                                                    <img src="{{ $image->url() }}" alt="{{ $image->alt ?? '' }}" class="aspect-square w-full rounded-md object-cover">
                                                    <input type="checkbox" name="images[]" value="{{ $image->id }}"
                                                           class="absolute top-1 left-1 accent-indigo-600" @checked($place !== null)>
                                                    <span class="sr-only">Show {{ $image->alt ?? 'this picture' }} in this gallery</span>
                                                </label>
                                                <label class="mt-1 block">
                                                    <span class="sr-only">{{ $image->alt ?: 'This picture' }}'s place in the gallery</span>
                                                    <input type="number" name="order[{{ $image->id }}]" min="0" max="{{ $page->maxGalleryImages }}"
                                                           value="{{ $failed ? old('order.'.$image->id, $place) : $place }}"
                                                           class="{{ $input }} mt-0 px-1.5 py-0.5 text-center text-xs">
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="{{ $hint }}">Tick the pictures to show and number them from 0. Up to {{ $page->maxGalleryImages }}.</p>
                                    @foreach (['images', 'images.*', 'order.*'] as $key)
                                        @if ($bag->has($key))
                                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $bag->first($key) }}</p>
                                        @endif
                                    @endforeach
                                </fieldset>
                            @endif
                        </form>

                        <div class="-mt-3 flex items-center gap-2">
                            @foreach (['up' => 'Move up', 'down' => 'Move down'] as $direction => $labelText)
                                <form method="POST" action="{{ route('seller.store.sections.reorder', $section) }}">
                                    @csrf
                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                    <button type="submit" class="{{ $secondarySmall }}" @disabled($direction === 'up' ? $loop->parent->first : $loop->parent->last)>{{ $labelText }}</button>
                                </form>
                            @endforeach
                            <form method="POST" action="{{ route('seller.store.sections.destroy', $section) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="{{ $secondarySmall }}">Remove<span class="sr-only"> {{ $section->kind->label() }} section</span></button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Nothing on your page yet. Add a story or a gallery.</p>
                    @endforelse

                    @if ($page->profile->sections->count() < $page->maxSections)
                        <div class="flex flex-wrap gap-3">
                            @foreach ($page->sectionKinds as $kind)
                                <form method="POST" action="{{ route('seller.store.sections.store') }}">
                                    @csrf
                                    <input type="hidden" name="kind" value="{{ $kind->value }}">
                                    <button type="submit" class="{{ $secondary }}">Add a {{ strtolower($kind->label()) }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

        <aside class="xl:sticky xl:top-20">
            <p class="mb-2 text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">How buyers see it</p>
            <x-store.profile :profile="$page->profile" :facts="$page->facts" compact />
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-500">The storefront renders in the Warm Craft theme; this preview uses its tokens.</p>
        </aside>
    </div>
</x-layouts.seller>
