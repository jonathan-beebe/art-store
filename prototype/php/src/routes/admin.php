<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CustomerBlockController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LiftCustomerBlockController;
use App\Http\Controllers\Admin\SellerController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware('auth.admin')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('sellers', [SellerController::class, 'index'])->name('sellers.index');
    Route::get('sellers/{seller}', [SellerController::class, 'show'])->name('sellers.show');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    Route::post('customers/{customer}/blocks', CustomerBlockController::class)->name('customers.blocks.store');
    Route::post('customers/{customer}/blocks/lift', LiftCustomerBlockController::class)->name('customers.blocks.lift');
});
