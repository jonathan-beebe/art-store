<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Featured
    |--------------------------------------------------------------------------
    |
    | The one listing or category the home page's featured band shows, set by
    | hand and unchanged until this is edited again (DSGN-007). `type` is
    | "listing" (`value` is a slug) or "category" (`value` is a
    | `/browse/{categoryPath}` path, without slashes). When the value names a
    | listing no longer for sale, or a category that no longer exists, is not
    | browsable, or carries no for-sale listing, the band renders nothing —
    | {@see \App\Support\Shop\FeaturedSubject::resolve()} answers null and the
    | page shows no broken card and no substitute.
    |
    */

    'featured' => [
        'type' => env('STOREFRONT_FEATURED_TYPE', 'listing'),
        'value' => env('STOREFRONT_FEATURED_VALUE', 'the-burrow-at-dusk'),
    ],

];
