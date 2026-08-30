<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Chrome DevTools probes this well-known path on every open DevTools
// session. Answering 204 keeps the probe out of the 404 page and keeps
// the log list free of a red status badge per DevTools window.
Route::get('/.well-known/appspecific/com.chrome.devtools.json', fn () => response()->noContent());

require __DIR__.'/auth.php';
require __DIR__.'/shop.php';
require __DIR__.'/seller.php';
require __DIR__.'/admin.php';
