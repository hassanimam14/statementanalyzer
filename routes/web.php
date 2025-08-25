<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CoachingController;

// Public route
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('landing');
})->name('landing');

// Authentication routes
require __DIR__.'/auth.php';

// Authenticated routes
// routes/web.php
// routes/web.php
Route::get('/auth', fn () => view('auth.portal'))->name('auth.portal');
Route::get('/healthz', fn() => 'ok');


Route::middleware(['auth'])->group(function () {
    // ✅ Dashboard (pointing to show instead of index)
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');

    // Alerts actions
    Route::post('/alerts/{statement}/resolve', [DashboardController::class, 'resolve'])
    ->middleware(['auth'])->name('alerts.resolve');

Route::post('/alerts/{statement}/dispute', [DashboardController::class, 'dispute'])
    ->middleware(['auth'])->name('alerts.dispute');
    // Coaching (from latest report tips)
    Route::get('/coaching', [CoachingController::class, 'index'])->name('coaching.index');

    // Reports
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::post('/reports/{statement}/generate', [ReportController::class, 'generate'])->name('reports.generate');
Route::get('/reports/{statement}', [ReportController::class, 'show'])->name('reports.show');

    // Statements
    Route::get('/statements', [StatementController::class, 'index'])->name('statements.index');
    Route::get('/statements/upload', [StatementController::class, 'create'])->name('statements.create');
    Route::post('/statements', [StatementController::class, 'store'])->name('statements.store');
    Route::get('/statements/{statement}/transactions', [StatementController::class, 'show'])->name('statements.show');
    Route::delete('/statements/{statement}', [StatementController::class, 'destroy'])
    ->middleware('auth')
    ->name('statements.destroy');
    Route::post('/statements/{statement}/analyze', [StatementController::class, 'analyze'])
    ->name('statements.analyze');

    Route::get('/statements/{statement}/status', [StatementController::class, 'status'])
    ->name('statements.status');
// routes/web.php
Route::get('/reports/{statement}/download', [ReportController::class, 'download'])
     ->name('reports.download');


    // Educational Resources
    Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
    Route::get('/resources/{slug}', [ResourceController::class, 'show'])->name('resources.show');

    // Cards
    Route::get('/cards', [CardController::class, 'index'])->name('cards.index');
    Route::get('/cards/create', [CardController::class, 'create'])->name('cards.create');
    Route::post('/cards', [CardController::class, 'store'])->name('cards.store');
    Route::get('/cards/{card}/edit', [CardController::class, 'edit'])->name('cards.edit');
    Route::put('/cards/{card}', [CardController::class, 'update'])->name('cards.update');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
// routes/web.php
Route::get('/gemini-check', function () {
    return [
        'has_key'  => trim((string)config('services.gemini.api_key')) !== '',
        'len'      => strlen((string)config('services.gemini.api_key')),
        'endpoint' => config('services.gemini.endpoint'),
        'model'    => config('services.gemini.model'),
    ];
});

