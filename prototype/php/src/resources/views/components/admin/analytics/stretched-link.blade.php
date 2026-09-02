{{-- An invisible link filling a `relative` table cell, so a row with
     several columns is a single tap target. Safari does not treat a `tr`
     as a positioned ancestor, so each cell stretches its own link rather
     than one link stretching across the row. --}}
@props(['href'])

<a href="{{ $href }}" tabindex="-1" aria-hidden="true" class="absolute inset-0"></a>
