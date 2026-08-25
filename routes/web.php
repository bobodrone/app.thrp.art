<?php

use App\Http\Controllers\AboutPageController;
use App\Http\Controllers\AdminBootstrapController;
use App\Http\Controllers\CreatorAnsweredController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MyQuestionsController;
use App\Http\Controllers\PublicCreatorController;
use App\Http\Controllers\QuestionClaimController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SettingsController;
use App\Livewire\AdminApplications;
use App\Livewire\AdminQuestionsTable;
use App\Livewire\AdminUserManagement;
use App\Livewire\CreatorApplicationForm;
use App\Livewire\CreatorDashboard;
use App\Livewire\CreatorProfile;
use App\Livewire\CreatorQuestionDetail;
use App\Livewire\CreatorsIndex;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

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

// Public legal info page
Route::get('/about', [AboutPageController::class, 'show'])->name('about');

// Public question detail
Route::get('/questions/{question}', [QuestionController::class, 'show'])->name('questions.show');

// Public responder directory
Route::get('/responders', CreatorsIndex::class)->name('creators.index');
Route::get('/responders/{user}', [PublicCreatorController::class, 'show'])->name('creators.show');

// Public responder application
Route::get('/apply', CreatorApplicationForm::class)->name('apply');

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
    Route::post('/settings/name', [SettingsController::class, 'updateName'])->name('settings.name');
    Route::post('/settings/email', [SettingsController::class, 'changeEmail'])->name('settings.email');
    Route::post('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');

    // Member's own questions
    Route::get('/my-questions', [MyQuestionsController::class, 'index'])->name('my-questions');
});

// Responder area (responder or admin). The stored role value is still 'creator'.
Route::middleware(['auth', 'role:creator,admin'])->prefix('responder')->name('creator.')->group(function () {
    Route::get('/', CreatorDashboard::class)->name('dashboard');
    Route::get('/profile', CreatorProfile::class)->name('profile');
    Route::get('/answered', [CreatorAnsweredController::class, 'index'])->name('answered');
    Route::get('/questions/{question}', CreatorQuestionDetail::class)->name('questions.show');
    Route::post('/questions/{question}/claim', [QuestionClaimController::class, 'store'])->name('questions.claim');
});

// Admin area (built out in Phases 6–7 — stubs)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/questions', AdminQuestionsTable::class)->name('questions');
    // Every account, whatever its role — find, edit, block, anonymise.
    Route::get('/users', AdminUserManagement::class)->name('users');
    // Just the responder application queue; the accounts it creates are managed above.
    Route::get('/applications', AdminApplications::class)->name('applications');
});

// Legacy /creator(s) URLs, kept alive for bookmarks and inbound links after the
// rename to "responder". Declared last so they never shadow a real route.

Route::redirect('/creators', '/responders', 301);
Route::redirect('/creators/{user}', '/responders/{user}', 301);
Route::redirect('/admin/creators', '/admin/applications', 301);
Route::redirect('/admin/responders', '/admin/applications', 301);
Route::redirect('/creator', '/responder', 301);

// Everything under the old answerer-area prefix, split by verb rather than
// declared with Route::redirect(): a 301 tells the client to re-send a POST as
// a GET, which would drop the body. 308 keeps the method intact. The claim
// button is the only form that posts here, and only a page left open across
// the deploy can still aim at the old path.
Route::get('/creator/{path}', fn (string $path) => redirect('/responder/' . $path, 301))
    ->where('path', '.*');
Route::post('/creator/{path}', fn (string $path) => redirect('/responder/' . $path, 308))
    ->where('path', '.*');

require __DIR__ . '/auth.php';
