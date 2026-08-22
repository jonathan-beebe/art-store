<?php

use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\DeliveryConfirmationController;
use App\Http\Controllers\Shop\FavoriteController;
use App\Http\Controllers\Shop\ListingController;
use App\Http\Controllers\Shop\OrderController;
use App\Http\Controllers\Shop\OrderPaymentController;
use App\Http\Controllers\Shop\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::middleware('customer.identity')->name('shop.')->group(function (): void {
    Route::get('/', StorefrontController::class)->name('home');
    Route::get('/art/{listing:slug}', ListingController::class)->name('listing');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites');
    Route::post('/art/{listing:slug}/favorite', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart/{listing:slug}', [CartController::class, 'add'])->name('cart.add');
    Route::delete('/cart/{listing:slug}', [CartController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('order');
    Route::get('/orders/{order}/pay', [OrderPaymentController::class, 'show'])->name('order.pay');
    Route::post('/orders/{order}/pay', [OrderPaymentController::class, 'pay'])->name('order.pay.submit');
    Route::post('/orders/{order}/fulfillments/{fulfillment}/delivered', DeliveryConfirmationController::class)
        ->name('order.delivered');

    Route::middleware('auth.customer')->group(function (): void {
        Route::get('/account', [AccountController::class, 'show'])->name('account');
        Route::post('/account/notifications/{notification}/read', [AccountController::class, 'readNotification'])
            ->name('account.notifications.read');
    });
});
