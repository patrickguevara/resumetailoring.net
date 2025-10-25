<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobEvaluationController;
use App\Http\Controllers\JobResearchController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\ResumeEvaluationController;
use App\Http\Controllers\TailoredResumeController;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/privacy', function () {
    return Inertia::render('PrivacyPolicy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('TermsOfService');
})->name('terms');

Route::post('/stripe/webhook', [CashierWebhookController::class, 'handleWebhook']);

Route::get('/pricing', function () {
    return Inertia::render('Pricing');
})->name('pricing');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::get('jobs/{job}', [JobController::class, 'show'])->name('jobs.show');
    Route::post('jobs/{job}/evaluations', [JobEvaluationController::class, 'store'])
        ->name('jobs.evaluations.store');
    Route::post('jobs/{job}/research', [JobResearchController::class, 'store'])
        ->name('jobs.research.store');

    Route::get('resumes', [ResumeController::class, 'index'])->name('resumes.index');
    Route::post('resumes', [ResumeController::class, 'store'])->name('resumes.store');
    Route::get('resumes/{resume}', [ResumeController::class, 'show'])->name('resumes.show');

    Route::post('resumes/{resume}/evaluations', [ResumeEvaluationController::class, 'store'])
        ->name('resumes.evaluations.store');
    Route::get('evaluations/{evaluation}', [ResumeEvaluationController::class, 'show'])
        ->name('evaluations.show');
    Route::post('evaluations/{evaluation}/tailor', [TailoredResumeController::class, 'store'])
        ->name('evaluations.tailor');
    Route::get('tailored-resumes/{tailoredResume}', [TailoredResumeController::class, 'show'])
        ->name('tailored-resumes.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
