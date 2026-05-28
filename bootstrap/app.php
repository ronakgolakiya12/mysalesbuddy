<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyRecallWebhookSignature;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use App\Exceptions\CoachingOutputInvalidException;
use App\Exceptions\TranscriptTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);
        $middleware->api(append: [
            ForceJsonResponse::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/*',
        ]);
        $middleware->alias([
            'recall.webhook' => VerifyRecallWebhookSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([
            CoachingOutputInvalidException::class,
            TranscriptTooLargeException::class,
        ]);

        $exceptions->report(function (CoachingOutputInvalidException $e) {
            Log::error('Coaching output invalid', ['exception' => $e->getMessage()]);
        });

        $exceptions->report(function (TranscriptTooLargeException $e) {
            Log::warning('Transcript too large', ['exception' => $e->getMessage()]);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.'], 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => 'Resource not found.'], 404);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json(['message' => 'Too many requests. Try again later.'], 429);
        });

        $exceptions->render(function (\App\Exceptions\UnsupportedMeetingProviderException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'external_meeting_url' => [$e->getMessage()],
                ],
            ], 422);
        });

        $exceptions->render(function (\App\Exceptions\DuplicateBotException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'conflicting_meeting_id' => $e->conflictingMeetingId,
            ], 409);
        });

        $exceptions->render(function (\App\Exceptions\CalendarNotConnectedException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'calendar_not_connected',
            ], 422);
        });

        $exceptions->render(function (\App\Exceptions\CalendarTokenExpiredException $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'calendar_token_expired',
            ], 422);
        });
    })->create();
