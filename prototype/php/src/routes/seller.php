<?php

declare(strict_types=1);

use App\Http\Controllers\Seller\BulkVariantsController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\DeclineController;
use App\Http\Controllers\Seller\DescriptionSectionController;
use App\Http\Controllers\Seller\DescriptionSectionReorderController;
use App\Http\Controllers\Seller\EarningsController;
use App\Http\Controllers\Seller\GenerateVariantsController;
use App\Http\Controllers\Seller\ListingAttributeController;
use App\Http\Controllers\Seller\ListingBasicsController;
use App\Http\Controllers\Seller\ListingController;
use App\Http\Controllers\Seller\ListingFaqController;
use App\Http\Controllers\Seller\ListingImageController;
use App\Http\Controllers\Seller\ListingImageReorderController;
use App\Http\Controllers\Seller\ListingStatusController;
use App\Http\Controllers\Seller\MessageController;
use App\Http\Controllers\Seller\ModifierController;
use App\Http\Controllers\Seller\ModifierOptionController;
use App\Http\Controllers\Seller\ModifierScopeController;
use App\Http\Controllers\Seller\NotificationController;
use App\Http\Controllers\Seller\OptionAxisController;
use App\Http\Controllers\Seller\OptionValueController;
use App\Http\Controllers\Seller\OrderController;
use App\Http\Controllers\Seller\OrderMessageController;
use App\Http\Controllers\Seller\QuantityBreakController;
use App\Http\Controllers\Seller\ShipmentController;
use App\Http\Controllers\Seller\SupportController;
use App\Http\Controllers\Seller\UnitController;
use App\Http\Controllers\Seller\VariantController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->name('seller.')->middleware('auth.seller')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::resource('listings', ListingController::class)->except('destroy');
    Route::get('listings/{listing}/basics', [ListingBasicsController::class, 'edit'])->name('listings.basics.edit');
    Route::post('listings/{listing}/status', ListingStatusController::class)->name('listings.status');
    Route::put('listings/{listing}/attributes', [ListingAttributeController::class, 'update'])->name('listings.attributes.update');
    Route::resource('listings.faqs', ListingFaqController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped();

    Route::resource('listings.images', ListingImageController::class)
        ->only(['index', 'store', 'destroy'])
        ->scoped();
    Route::post('listings/{listing}/images/{image}/reorder', ListingImageReorderController::class)
        ->name('listings.images.reorder')
        ->scopeBindings();

    Route::resource('listings.option-axes', OptionAxisController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped();
    Route::resource('listings.option-axes.option-values', OptionValueController::class)
        ->only(['store', 'update', 'destroy'])
        ->scoped();

    Route::resource('listings.variants', VariantController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped();
    Route::post('listings/{listing}/variants/generate', GenerateVariantsController::class)->name('listings.variants.generate');
    Route::post('listings/{listing}/variants/bulk', BulkVariantsController::class)->name('listings.variants.bulk');
    Route::resource('listings.variants.units', UnitController::class)
        ->only(['index', 'store', 'update'])
        ->scoped();

    Route::resource('listings.modifiers', ModifierController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped();
    Route::post('listings/{listing}/modifiers/{modifier}/scope', ModifierScopeController::class)
        ->name('listings.modifiers.scope')
        ->scopeBindings();
    Route::resource('listings.modifiers.options', ModifierOptionController::class)
        ->only(['store', 'update', 'destroy'])
        ->scoped();

    Route::resource('listings.quantity-breaks', QuantityBreakController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped();

    Route::resource('listings.description-sections', DescriptionSectionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->scoped();
    Route::post('listings/{listing}/description-sections/{description_section}/reorder', DescriptionSectionReorderController::class)
        ->name('listings.description-sections.reorder')
        ->scopeBindings();

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

    Route::get('support', SupportController::class)->name('support');
});
