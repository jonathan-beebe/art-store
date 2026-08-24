<?php

declare(strict_types=1);

use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\DeclineController;
use App\Http\Controllers\Seller\EarningsController;
use App\Http\Controllers\Seller\EventsController;
use App\Http\Controllers\Seller\ListingController;
use App\Http\Controllers\Seller\ListingFaqController;
use App\Http\Controllers\Seller\ListingStatusController;
use App\Http\Controllers\Seller\MessageController;
use App\Http\Controllers\Seller\NotificationController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\OrderMessageController;
use App\Http\Controllers\Seller\ShipmentController;
use App\Http\Controllers\Seller\SupportController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->name('seller.')->middleware('auth.seller')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('listings', ListingController::class)->except('destroy');
    Route::post('listings/{listing}/status', ListingStatusController::class)->name('listings.status');
    Route::resource('listings.faqs', ListingFaqController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped();

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{fulfillment}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{fulfillment}/shipment', ShipmentController::class)->name('orders.ship');
    Route::post('orders/{fulfillment}/decline', DeclineController::class)->name('orders.decline');
    Route::post('orders/{fulfillment}/messages', OrderMessageController::class)->name('orders.messages');

    Route::get('earnings', EarningsController::class)->name('earnings');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
    Route::get('events', EventsController::class)->name('events');

    Route::get('support', SupportController::class)->name('support');
});
