<?php

declare(strict_types=1);

use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\EarningsController;
use App\Http\Controllers\Seller\ListingActivityController;
use App\Http\Controllers\Seller\ListingController;
use App\Http\Controllers\Seller\ListingStatusController;
use App\Http\Controllers\Seller\NotificationController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\PayoutController;
use App\Http\Controllers\Seller\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->name('seller.')->middleware('auth.seller')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('listings/create', [ListingController::class, 'create'])->name('listings.create');
    Route::post('listings', [ListingController::class, 'store'])->name('listings.store');
    Route::get('listings/{listing}', ListingActivityController::class)->name('listings.show');
    Route::get('listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
    Route::post('listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::post('listings/{listing}/status', ListingStatusController::class)->name('listings.status');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{fulfillment}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{fulfillment}/shipment', ShipmentController::class)->name('orders.ship');

    Route::get('earnings', EarningsController::class)->name('earnings');
    Route::post('earnings/payouts', PayoutController::class)->name('earnings.payout');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});
