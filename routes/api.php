<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\OAuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\CoachingController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Settings\NotetakerConfigController;
use App\Http\Controllers\Api\Settings\NotificationPreferencesController;
use App\Http\Controllers\Api\Settings\PromptController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::any('/webhooks/recall', [WebhookController::class, 'recallWebhook'])
    ->middleware(['recall.webhook', 'throttle:600,1']);

Route::middleware(['web'])->group(function () {
    Route::get('/oauth/google/callback', [OAuthController::class, 'handleGoogleCallback'])
        ->name('oauth.google.callback');
});

Route::middleware('guest')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:20,1');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    Route::prefix('auth/oauth')->group(function () {
        Route::get('/google/redirect', [OAuthController::class, 'redirectToGoogle'])
            ->middleware('throttle:10,1')
            ->name('oauth.google.redirect');
        Route::delete('/google', [OAuthController::class, 'disconnectGoogle'])->name('oauth.google.disconnect');

        Route::get('/microsoft/redirect', static fn() => response()->json([
            'message' => 'Microsoft OAuth is not implemented.',
        ], 501))->middleware('throttle:10,1')->name('oauth.microsoft.redirect');

        Route::delete('/microsoft', static fn() => response()->json([
            'message' => 'Microsoft OAuth is not implemented.',
        ], 501))->name('oauth.microsoft.disconnect');
    });

    Route::get('/calendar/events', [CalendarController::class, 'upcomingEvents'])->name('calendar.events');

    Route::get('/meetings', [MeetingController::class, 'index']);
    Route::post('/meetings', [MeetingController::class, 'store']);
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
    Route::get('/meetings/{meeting}/transcript', [MeetingController::class, 'transcript']);
    Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy']);
    Route::post('/meetings/{meeting}/cancel-dispatch', [MeetingController::class, 'cancelDispatch']);
    Route::post('/meetings/{meeting}/export-pdf', [MeetingController::class, 'exportPdf']);

    Route::prefix('meetings/{meeting}')->group(function () {
        Route::get('/coaching', [CoachingController::class, 'show'])->name('coaching.show');
        Route::post('/coaching/trigger', [CoachingController::class, 'trigger'])->name('coaching.trigger');
    });
    Route::patch('/coaching-analyses/{analysis}/rate', [CoachingController::class, 'rateItem'])->name('coaching.rate');

    Route::get('/settings/notetaker', [NotetakerConfigController::class, 'show']);
    Route::patch('/settings/notetaker', [NotetakerConfigController::class, 'update']);
    Route::post('/settings/notetaker/avatar', [NotetakerConfigController::class, 'uploadAvatar'])
        ->middleware('throttle:20,1');

    Route::get('/settings/notifications', [NotificationPreferencesController::class, 'show']);
    Route::patch('/settings/notifications', [NotificationPreferencesController::class, 'update']);

    Route::prefix('settings/prompt')->group(function () {
        Route::get('/', [PromptController::class, 'index'])->name('settings.prompt.index');
        Route::post('/', [PromptController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('settings.prompt.store');
        Route::post('/{version}/restore', [PromptController::class, 'restore'])->name('settings.prompt.restore');
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('/{notification}/read', [NotificationController::class, 'markRead']);
    });
});

Route::fallback(fn() => response()->json(['message' => 'API endpoint not found.'], 404));
