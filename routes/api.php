<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChapterController;
use App\Http\Controllers\GroupChatController;
use App\Http\Controllers\PrivateMessageController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

/* Messages use browser/session semantics so the same-origin UI can send CSRF-protected JSON. */
Route::middleware('web')->group(function (): void {
    Route::get('/recommendations', [RecommendationController::class, 'index'])->name('api.recommendations.index');
    Route::get('/recommendations/stream', [RecommendationController::class, 'stream'])->name('api.recommendations.stream');
    Route::get('/novels/{novel:slug}/reviews', [PublicController::class, 'reviews'])->name('api.novels.reviews');
});

Route::middleware(['web', 'auth'])
    ->group(function (): void {
        Route::get('/messages/users', [PrivateMessageController::class, 'users'])->name('api.messages.users');
        Route::get('/messages', [PrivateMessageController::class, 'index'])->name('api.messages.index');
        Route::get('/messages/{conversation}', [PrivateMessageController::class, 'show'])->name('api.messages.show');
        Route::post('/messages', [PrivateMessageController::class, 'store'])->name('api.messages.store');
        Route::post('/messages/{conversation}/read', [PrivateMessageController::class, 'markRead'])->name('api.messages.read');
        Route::get('/messages/{conversation}/stream', [PrivateMessageController::class, 'stream'])->name('api.messages.stream');

        Route::get('/groups', [GroupChatController::class, 'index'])->name('api.groups.index');
        Route::get('/groups/{group}', [GroupChatController::class, 'show'])->name('api.groups.show');
        Route::post('/groups', [GroupChatController::class, 'store'])->name('api.groups.store');
        Route::post('/groups/{group}/members', [GroupChatController::class, 'addMember'])->name('api.groups.members.add');
        Route::delete('/groups/{group}/members/{user}', [GroupChatController::class, 'removeMember'])->name('api.groups.members.remove');
        Route::post('/groups/{group}/messages', [GroupChatController::class, 'sendMessage'])->name('api.groups.messages.store');
        Route::post('/groups/{group}/read', [GroupChatController::class, 'markRead'])->name('api.groups.read');
        Route::get('/groups/{group}/stream', [GroupChatController::class, 'stream'])->name('api.groups.stream');
    });

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
