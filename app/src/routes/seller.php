<?php

declare(strict_types=1);

use App\Domain\Analytics\AnalyticsEventName;
use App\Http\Controllers\Seller\BulkVariantsController;
use App\Http\Controllers\Seller\CustomerController;
use App\Http\Controllers\Seller\CustomerMessageController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\DeclineController;
use App\Http\Controllers\Seller\DescriptionSectionController;
use App\Http\Controllers\Seller\DescriptionSectionReorderController;
use App\Http\Controllers\Seller\EarningsController;
use App\Http\Controllers\Seller\FlowStepController;
use App\Http\Controllers\Seller\FulfillmentFlowController;
use App\Http\Controllers\Seller\GenerateVariantsController;
use App\Http\Controllers\Seller\HelpArticleController;
use App\Http\Controllers\Seller\HelpArticleFeedbackController;
use App\Http\Controllers\Seller\LegacyFlowRedirectController;
use App\Http\Controllers\Seller\ListingAttributeController;
use App\Http\Controllers\Seller\ListingBasicsController;
use App\Http\Controllers\Seller\ListingController;
use App\Http\Controllers\Seller\ListingFaqController;
use App\Http\Controllers\Seller\ListingImageController;
use App\Http\Controllers\Seller\ListingImageReorderController;
use App\Http\Controllers\Seller\ListingStatusController;
use App\Http\Controllers\Seller\MakeFulfillmentFlowDefaultController;
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
use App\Http\Controllers\Seller\ReopenConversationController;
use App\Http\Controllers\Seller\ResolveConversationController;
use App\Http\Controllers\Seller\ShipmentController;
use App\Http\Controllers\Seller\ShippingLabelController;
use App\Http\Controllers\Seller\StatementController;
use App\Http\Controllers\Seller\StoreController;
use App\Http\Controllers\Seller\StoreImageController;
use App\Http\Controllers\Seller\StoreSectionController;
use App\Http\Controllers\Seller\StoreSectionReorderController;
use App\Http\Controllers\Seller\SupportController;
use App\Http\Controllers\Seller\UnitController;
use App\Http\Controllers\Seller\VariantController;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->name('seller.')->middleware('auth.seller')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::singleton('store', StoreController::class)->only(['show', 'update']);
    Route::post('store/images', [StoreImageController::class, 'store'])->name('store.images.store');
    Route::delete('store/images/{image}', [StoreImageController::class, 'destroy'])->name('store.images.destroy');
    Route::post('store/sections', [StoreSectionController::class, 'store'])->name('store.sections.store');
    Route::put('store/sections/{section}', [StoreSectionController::class, 'update'])->name('store.sections.update');
    Route::delete('store/sections/{section}', [StoreSectionController::class, 'destroy'])->name('store.sections.destroy');
    Route::post('store/sections/{section}/reorder', StoreSectionReorderController::class)->name('store.sections.reorder');

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
    // Declared before `orders/{fulfillment}` so the old flow link's path is
    // not read as a fulfillment id.
    Route::get('orders/flow', LegacyFlowRedirectController::class)->name('orders.flow.edit');
    Route::get('orders/{fulfillment}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('orders/{fulfillment}/label', ShippingLabelController::class)->name('orders.label');
    Route::post('orders/{fulfillment}/steps/{step}', FlowStepController::class)->name('orders.steps.complete');
    Route::post('orders/{fulfillment}/shipment', ShipmentController::class)->name('orders.ship');
    Route::post('orders/{fulfillment}/decline', DeclineController::class)->name('orders.decline');
    Route::post('orders/{fulfillment}/messages', OrderMessageController::class)->name('orders.messages');

    Route::resource('workflows', FulfillmentFlowController::class)->except('show');
    Route::post('workflows/{workflow}/default', MakeFulfillmentFlowDefaultController::class)->name('workflows.default');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::post('customers/{customer}/messages', CustomerMessageController::class)->name('customers.messages');

    Route::get('earnings', EarningsController::class)->name('earnings');
    Route::get('earnings/statements/{period}', StatementController::class)->name('earnings.statements.show');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('messages/{conversation}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('messages/{conversation}', [MessageController::class, 'store'])->name('messages.store');
    Route::post('messages/{conversation}/resolve', ResolveConversationController::class)->name('messages.resolve');
    Route::post('messages/{conversation}/reopen', ReopenConversationController::class)->name('messages.reopen');

    Route::get('support', [SupportController::class, 'index'])->name('support');
    Route::get('support/new', [SupportController::class, 'create'])->name('support.create');
    Route::post('support/new', [SupportController::class, 'store'])->name('support.store');
    Route::get('support/articles/{article}', [HelpArticleController::class, 'show'])->name('support.articles.show');
    Route::post('support/articles/{article}/answered', HelpArticleFeedbackController::class)
        ->defaults('outcome', AnalyticsEventName::HelpAnswered->value)
        ->name('support.articles.answered');
    Route::post('support/articles/{article}/unanswered', HelpArticleFeedbackController::class)
        ->defaults('outcome', AnalyticsEventName::HelpUnanswered->value)
        ->name('support.articles.unanswered');
});
