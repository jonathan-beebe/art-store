<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AccountingController;
use App\Http\Controllers\Admin\Analytics\ActorController;
use App\Http\Controllers\Admin\Analytics\AnalyticsController;
use App\Http\Controllers\Admin\Analytics\ChannelController;
use App\Http\Controllers\Admin\Analytics\EventController;
use App\Http\Controllers\Admin\Analytics\FunnelController as AnalyticsFunnelController;
use App\Http\Controllers\Admin\Analytics\ListingController as AnalyticsListingController;
use App\Http\Controllers\Admin\CustomerBlockController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FulfillmentController;
use App\Http\Controllers\Admin\FunnelController;
use App\Http\Controllers\Admin\FunnelDeleteController;
use App\Http\Controllers\Admin\LedgerController;
use App\Http\Controllers\Admin\LiftCustomerBlockController;
use App\Http\Controllers\Admin\LiftListingRemovalController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\ListingRemovalController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\OrderCancellationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\ReopenConversationController;
use App\Http\Controllers\Admin\ResolveConversationController;
use App\Http\Controllers\Admin\RunPayoutController;
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
    Route::post('listings/{listing}/removals', ListingRemovalController::class)->name('listings.removals.store');
    Route::post('listings/{listing}/removals/lift', LiftListingRemovalController::class)->name('listings.removals.lift');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/cancel', OrderCancellationController::class)->name('orders.cancel');

    Route::get('fulfillments', [FulfillmentController::class, 'index'])->name('fulfillments.index');
    Route::get('fulfillments/{fulfillment}', [FulfillmentController::class, 'show'])->name('fulfillments.show');
    Route::post('fulfillments/{fulfillment}/refund', RefundController::class)->name('fulfillments.refund');

    Route::resource('funnels', FunnelController::class)->except('show');
    Route::get('funnels/{funnel}/delete', FunnelDeleteController::class)->name('funnels.delete');

    Route::get('accounting', AccountingController::class)->name('accounting');
    Route::get('ledger', LedgerController::class)->name('ledger');
    Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
    Route::post('payouts', RunPayoutController::class)->name('payouts.run');
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/events/{name}', [EventController::class, 'show'])->name('analytics.events.show');
    Route::get('analytics/actors', [ActorController::class, 'index'])->name('analytics.actors.index');
    Route::get('analytics/actors/{customer}', [ActorController::class, 'show'])->name('analytics.actors.show');
    Route::get('analytics/listings/{listing}', [AnalyticsListingController::class, 'show'])->name('analytics.listings.show');
    Route::get('analytics/funnels/{funnel}', [AnalyticsFunnelController::class, 'show'])->name('analytics.funnels.show');
    Route::get('analytics/channels', [ChannelController::class, 'index'])->name('analytics.channels.index');
    Route::get('analytics/channels/{key}', [ChannelController::class, 'show'])->name('analytics.channels.show');
    Route::permanentRedirect('stats', '/admin/analytics');

    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
    Route::post('messages/{conversation}/resolve', ResolveConversationController::class)->name('messages.resolve');
    Route::post('messages/{conversation}/reopen', ReopenConversationController::class)->name('messages.reopen');

    Route::get('logs', [LogController::class, 'index'])->name('logs.index');
    Route::get('logs/requests/{requestId}', [LogController::class, 'show'])
        ->where('requestId', '[A-Za-z0-9_-]{1,64}')
        ->name('logs.story');
});
