<?php

use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\BillingPortalController;
use App\Http\Controllers\Settings\LinkedInController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SubscriptionCheckoutController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/billing', BillingController::class)->name('billing.edit');
    Route::post('settings/billing/checkout', SubscriptionCheckoutController::class)->name('billing.checkout');
    Route::post('settings/billing/portal', BillingPortalController::class)->name('billing.portal');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/linkedin', [LinkedInController::class, 'show'])
        ->name('linkedin.settings');

    Route::delete('settings/linkedin', [LinkedInController::class, 'destroy'])
        ->name('linkedin.destroy');
});
