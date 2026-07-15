<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'home'])->name('home');
Route::get('/novels', [PublicController::class, 'index'])->name('novels.index');
Route::get('/novels/{novel:slug}/chapters/{chapter:chapter_number}', [PublicController::class, 'chapter'])
    ->whereNumber('chapter')->scopeBindings()->name('novels.read');
Route::get('/novels/{novel:slug}', [PublicController::class, 'novel'])->name('novels.show');
Route::get('/chapters/{novel:slug}/{chapter:chapter_number}', [PublicController::class, 'chapter'])
    ->whereNumber('chapter')->scopeBindings()->name('chapters.show');

Route::get('/login', [AuthController::class, 'loginEndpoint'])->middleware('guest')->name('login.page');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
Route::match(['get', 'post'], '/two-factor/challenge', [TwoFactorController::class, 'challenge'])
    ->middleware('throttle:two-factor')->name('two-factor.challenge');

Route::match(['get', 'post'], '/register', [AuthController::class, 'registerEndpoint'])->middleware(['guest', 'throttle:register'])->name('register');
Route::get('/forgot-password', [AuthController::class, 'forgotPasswordPage'])->middleware('guest')->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware(['guest', 'throttle:password-reset'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'resetPasswordPage'])->middleware('guest')->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware(['guest', 'throttle:password-reset'])->name('password.update');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
        Route::get('/two-factor', [TwoFactorController::class, 'show'])->name('two-factor.show');
        Route::post('/two-factor/enable', [TwoFactorController::class, 'enable'])->middleware('throttle:two-factor')->name('two-factor.enable');
        Route::delete('/two-factor', [TwoFactorController::class, 'disable'])->middleware('throttle:two-factor')->name('two-factor.disable');
    });

    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('/reading-records', [InteractionController::class, 'readings'])->name('reading-records.index');
    Route::post('/novels/{novel}/rating', [InteractionController::class, 'rate'])->name('novels.rate');
    Route::post('/novels/{novel}/favorite', [InteractionController::class, 'favorite'])->name('novels.favorite');
    Route::delete('/novels/{novel}/favorite', [InteractionController::class, 'unfavorite'])->name('novels.unfavorite');

    Route::middleware('email.required')->group(function () {
        Route::get('/submissions', [SubmissionController::class, 'index'])->name('submissions.index');
        Route::post('/submissions', [SubmissionController::class, 'store'])->middleware('role:user,author,editor,admin')->name('submissions.store');
        Route::get('/submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');

        Route::get('/author/submissions', [SubmissionController::class, 'index'])->name('author.submissions');
        Route::post('/author/submissions', [SubmissionController::class, 'store'])->middleware('role:user,author,editor,admin')->name('author.submissions.store');
    });
});

Route::middleware(['auth', 'email.required', 'role:author,editor,admin'])->prefix('author')->group(function () {
    Route::post('/novels/{novel}/chapters', [ChapterController::class, 'store'])->name('author.chapters.store');
    Route::match(['put', 'patch'], '/novels/{novel}/chapters/{chapter}', [ChapterController::class, 'update'])->name('author.chapters.update');
    Route::delete('/novels/{novel}/chapters/{chapter}', [ChapterController::class, 'destroy'])->name('author.chapters.destroy');
});

Route::middleware(['auth', 'email.required', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/settings', [AdminController::class, 'settings'])->name('admin.settings');
    Route::put('/settings', [AdminController::class, 'updateSettings'])->name('admin.settings.update');
    Route::get('/categories', [AdminController::class, 'categories'])->name('admin.categories.index');
    Route::post('/categories', [AdminController::class, 'storeCategory'])->name('admin.categories.store');
    Route::match(['put', 'patch'], '/categories/{category}', [AdminController::class, 'updateCategory'])->name('admin.categories.update');
    Route::delete('/categories/{category}', [AdminController::class, 'destroyCategory'])->name('admin.categories.destroy');
    Route::get('/novels', [AdminController::class, 'novels'])->name('admin.novels.index');
    Route::post('/novels', [AdminController::class, 'storeNovel'])->name('admin.novels.store');
    Route::match(['put', 'patch'], '/novels/{novel}', [AdminController::class, 'updateNovel'])->name('admin.novels.update');
    Route::delete('/novels/{novel}', [AdminController::class, 'destroyNovel'])->name('admin.novels.destroy');
    Route::get('/submissions', [AdminController::class, 'submissions'])->name('admin.submissions.index');
    Route::put('/submissions/{submission}/review', [AdminController::class, 'reviewSubmission'])->name('admin.submissions.review');
    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('admin.audit-logs.index');
});

