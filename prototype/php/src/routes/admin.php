<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CustomerBlockController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventsController;
use App\Http\Controllers\Admin\FulfillmentController;
use App\Http\Controllers\Admin\LiftCustomerBlockController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\OrderCancellationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\SellerMessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware('auth.admin')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('sellers', [SellerController::class, 'index'])->name('sellers.index');
    Route::get('sellers/{seller}', [SellerController::class, 'show'])->name('sellers.show');
    Route::post('sellers/{seller}/messages', SellerMessageController::class)->name('sellers.messages');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::post('customers/{customer}/messages', CustomerMessageController::class)->name('customers.messages');

    Route::post('customers/{customer}/blocks', CustomerBlockController::class)->name('customers.blocks.store');
    Route::post('customers/{customer}/blocks/lift', LiftCustomerBlockController::class)->name('customers.blocks.lift');

    Route::get('listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('listings/{listing}', [ListingController::class, 'show'])->name('listings.show');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/cancel', OrderCancellationController::class)->name('orders.cancel');

    Route::get('fulfillments', [FulfillmentController::class, 'index'])->name('fulfillments.index');
    Route::get('fulfillments/{fulfillment}', [FulfillmentController::class, 'show'])->name('fulfillments.show');
    Route::post('fulfillments/{fulfillment}/refund', RefundController::class)->name('fulfillments.refund');

    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
    Route::get('events', EventsController::class)->name('events');
});
