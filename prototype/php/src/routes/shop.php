<?php

declare(strict_types=1);

use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\BrowseController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\DeliveryConfirmationController;
use App\Http\Controllers\Shop\DesignSystemController;
use App\Http\Controllers\Shop\DesignSystemSpecimenController;
use App\Http\Controllers\Shop\FavoriteController;
use App\Http\Controllers\Shop\ListingController;
use App\Http\Controllers\Shop\ListingQuestionController;
use App\Http\Controllers\Shop\MediumController;
use App\Http\Controllers\Shop\MessageController;
use App\Http\Controllers\Shop\OrderCancellationController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\OrderMessageController;
use App\Http\Controllers\Shop\OrderPaymentController;
use App\Http\Controllers\Shop\SearchController;
use App\Http\Controllers\Shop\StorefrontController;
use App\Http\Controllers\Shop\SupportController;
use Illuminate\Support\Facades\Route;

Route::middleware('customer.identity')->name('shop.')->group(function (): void {
    Route::get('/', StorefrontController::class)->name('home');
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/medium/{medium}', MediumController::class)->name('medium');
    // One or two slug segments — the depth the seeded taxonomy actually
    // reaches (a root, or a root and its child); no route exists for a
    // grandchild because the catalog has none.
    Route::get('/browse/{categoryPath}', BrowseController::class)
        ->where('categoryPath', '[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)?')
        ->name('browse');
    Route::get('/art/{listing:slug}', ListingController::class)->name('listing');
    Route::get('/design-system', DesignSystemController::class)->name('design-system');
    Route::get('/design-system/specimens/{specimen}', DesignSystemSpecimenController::class)->name('design-system.specimen');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites');
    Route::post('/art/{listing:slug}/favorite', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart/{listing:slug}', [CartController::class, 'add'])->name('cart.add');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('order');
    Route::post('/orders/{order}/cancel', OrderCancellationController::class)->name('order.cancel');
    Route::get('/orders/{order}/pay', [OrderPaymentController::class, 'show'])->name('order.pay');
    Route::post('/orders/{order}/pay', [OrderPaymentController::class, 'pay'])->name('order.pay.submit');
    Route::post('/orders/{order}/fulfillments/{fulfillment}/delivered', DeliveryConfirmationController::class)
        ->scopeBindings()
        ->name('order.delivered');
    Route::post('/orders/{order}/fulfillments/{fulfillment}/messages', OrderMessageController::class)
        ->scopeBindings()
        ->name('order.messages');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');

    // Asking needs a verified customer: a question or a support thread is
    // the start of a relationship, and a relationship needs an address to
    // reach.
    Route::middleware('auth.customer')->group(function (): void {
        Route::post('/art/{listing:slug}/questions', ListingQuestionController::class)->name('listing.questions');
        Route::get('/support', [SupportController::class, 'show'])->name('support');
        Route::post('/support', [SupportController::class, 'store'])->name('support.store');

        Route::get('/account', [AccountController::class, 'show'])->name('account');
        Route::post('/account/notifications/{notification}/read', [AccountController::class, 'readNotification'])
            ->name('account.notifications.read');
    });
});
