@props([
    'href',
    'label',
    'count' => null,
    'coverUrl' => null,
    'tint' => 'bg-tint-1',
    'tintText' => 'text-on-tint',
    'active' => false,
])

{{--
    The golden-ratio browse tile (DSGN-007): 1.618:1 at any width, via
    `aspect-[1.618/1]` rather than a fixed height — a fixed height only holds
    the ratio at one column count. One component for both the medium row's
    tiles (photo covers) and the category grid's tiles (photo cover when
    `MediumBrowse`/`CategoryBrowse` found one, `tint` otherwise), so a tile
    is the exact same markup and size wherever it renders — inside the media
    drawer included.
--}}
@if ($coverUrl !== null)
    <a href="{{ $href }}" @if ($active) aria-current="true" @endif
       style="background-image: url('{{ $coverUrl }}')"
       class="relative flex aspect-[1.618/1] items-end overflow-hidden rounded-card bg-cover bg-center hover:brightness-105 {{ $active ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
        <span class="bg-photo-scrim relative flex w-full items-baseline justify-between gap-2 px-3 pb-2 pt-6 text-on-photo">
            <span class="truncate text-sm font-bold">{{ $label }}</span>
            @if ($count !== null)
                <span class="text-[11px] opacity-75">{{ $count }}</span>
            @endif
        </span>
    </a>
@else
    <a href="{{ $href }}" @if ($active) aria-current="true" @endif
       class="flex aspect-[1.618/1] items-end justify-between gap-2 rounded-card p-3 text-sm font-semibold {{ $tintText }} {{ $tint }} hover:brightness-105 {{ $active ? 'outline-2 outline-offset-2 outline-accent' : '' }}">
        <span class="truncate">{{ $label }}</span>
        @if ($count !== null)
            <span class="text-[11px] font-semibold opacity-70">{{ $count }}</span>
        @endif
    </a>
@endif
