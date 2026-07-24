<?php

use App\Http\Controllers\AdminBootstrapController;
use App\Http\Controllers\CreatorAnsweredController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyQuestionsController;
use App\Http\Controllers\QuestionClaimController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

// Run cron
Route::get('/cron/run', function () {
    abort_unless(
        hash_equals(config('app.cron_token'), request('token', '')),
        404
    );

    Artisan::call('schedule:run');

    return response(Artisan::output(), 200)
        ->header('Content-Type', 'text/plain');
});

// Public home (ask form + question feed)
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/', [QuestionController::class, 'store'])->middleware('auth')->name('questions.store');

// Public question detail
Route::get('/questions/{question}', [QuestionController::class, 'show'])->name('questions.show');

// Public creator application
Route::get('/apply', \App\Livewire\CreatorApplicationForm::class)->name('apply');

// One-time admin bootstrap — public (self-disables once an admin exists)
Route::get('/admin/setup', [AdminBootstrapController::class, 'create'])->name('admin.setup');
Route::post('/admin/setup', [AdminBootstrapController::class, 'store'])->name('admin.setup.store');

// Change-email confirmation link — public (clicked from email, possibly while logged out)
Route::get('/email/change/{token}', [SettingsController::class, 'confirmNewEmail'])
    ->name('email.change.confirm');

// Authenticated member area
Route::middleware('auth')->group(function () {
    // Settings (Phase 2) — nickname / email / password
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings');
    Route::post('/settings/name',     [SettingsController::class, 'updateName'])->name('settings.name');
    Route::post('/settings/email',    [SettingsController::class, 'changeEmail'])->name('settings.email');
    Route::post('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');

    // Member's own questions
    Route::get('/my-questions', [MyQuestionsController::class, 'index'])->name('my-questions');
});

// Creator area (creator or admin)
Route::middleware(['auth', 'role:creator,admin'])->prefix('creator')->name('creator.')->group(function () {
    Route::get('/',               \App\Livewire\CreatorDashboard::class)->name('dashboard');
    Route::get('/answered',       [CreatorAnsweredController::class, 'index'])->name('answered');
    Route::get('/questions/{question}', \App\Livewire\CreatorQuestionDetail::class)->name('questions.show');
    Route::post('/questions/{question}/claim', [QuestionClaimController::class, 'store'])->name('questions.claim');
});

// Admin area (built out in Phases 6–7 — stubs)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/questions', \App\Livewire\AdminQuestionsTable::class)->name('questions');
    Route::get('/creators',  \App\Livewire\AdminCreatorManagement::class)->name('creators');
    Route::get('/users',     \App\Livewire\AdminUserManagement::class)->name('users');
});

require __DIR__.'/auth.php';
