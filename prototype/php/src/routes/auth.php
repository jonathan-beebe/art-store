<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\CustomerLoginController;
use App\Http\Controllers\Auth\MagicLinkVerificationController;
use App\Http\Controllers\Auth\SellerLoginController;
use App\Http\Controllers\Auth\SignOutController;
use Illuminate\Support\Facades\Route;

// Magic-link routes land here; all three sites share them, so they carry no
// prefix of their own.
Route::name('auth.')->group(function (): void {
    Route::get('/seller/login', [SellerLoginController::class, 'show'])->name('seller.login');
    Route::post('/seller/login', [SellerLoginController::class, 'send'])->name('seller.send');
    Route::post('/seller/logout', [SignOutController::class, 'seller'])->name('seller.logout');

    Route::get('/login', [CustomerLoginController::class, 'show'])->name('customer.login');
    Route::post('/login', [CustomerLoginController::class, 'send'])->name('customer.send');
    Route::post('/logout', [SignOutController::class, 'customer'])->name('customer.logout');

    Route::get('/admin/login', [AdminLoginController::class, 'show'])->name('admin.login');
    Route::post('/admin/login', [AdminLoginController::class, 'send'])->name('admin.send');
    Route::post('/admin/logout', [SignOutController::class, 'admin'])->name('admin.logout');

    // One endpoint for all three sites: the link row names the actor it signs in.
    Route::get('/auth/magic/{token}', MagicLinkVerificationController::class)->name('magic.verify');
});
