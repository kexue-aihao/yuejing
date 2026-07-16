<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChapterController;
use Illuminate\Support\Facades\Route;

/* Admin API uses browser/session semantics, including CSRF protection. */
Route::middleware(['web', 'auth', 'role:admin'])
    ->prefix('admin')
    ->as('api.admin.')
    ->group(function (): void {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::put('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/settings/email-test', [AdminController::class, 'testEmail'])->name('settings.email-test');

        Route::get('/categories', [AdminController::class, 'categories'])->name('categories.index');
        Route::post('/categories', [AdminController::class, 'storeCategory'])->name('categories.store');
        Route::match(['put', 'patch'], '/categories/{category}', [AdminController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [AdminController::class, 'destroyCategory'])->name('categories.destroy');

        Route::get('/novels', [AdminController::class, 'novels'])->name('novels.index');
        Route::post('/novels', [AdminController::class, 'storeNovel'])->name('novels.store');
        Route::match(['put', 'patch'], '/novels/{novel}', [AdminController::class, 'updateNovel'])->name('novels.update');
        Route::delete('/novels/{novel}', [AdminController::class, 'destroyNovel'])->name('novels.destroy');
        Route::get('/novels/{novel}/chapters', [ChapterController::class, 'index'])->name('chapters.index');
        Route::post('/novels/{novel}/chapters', [ChapterController::class, 'store'])->name('chapters.store');
        Route::match(['put', 'patch'], '/novels/{novel}/chapters/{chapter}', [ChapterController::class, 'update'])->name('chapters.update');
        Route::delete('/novels/{novel}/chapters/{chapter}', [ChapterController::class, 'destroy'])->name('chapters.destroy');

        Route::get('/submissions', [AdminController::class, 'submissions'])->name('submissions.index');
        Route::put('/submissions/{submission}/review', [AdminController::class, 'reviewSubmission'])->name('submissions.review');
        Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs.index');
    });
