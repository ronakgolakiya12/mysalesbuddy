<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RecallAiService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyRecallWebhookSignature
{
    public function __construct(private readonly RecallAiService $recall)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Recall.ai sends webhook-* headers (Standard Webhooks spec).
        // Legacy webhooks still send svix-* — accept either.
        $id = (string) ($request->header('webhook-id') ?? $request->header('svix-id') ?? '');
        $timestamp = (string) ($request->header('webhook-timestamp') ?? $request->header('svix-timestamp') ?? '');
        $signature = (string) ($request->header('webhook-signature') ?? $request->header('svix-signature') ?? '');
        $body = (string) $request->getContent();

        if ($id === '' || $timestamp === '' || $signature === '') {
            Log::warning('Recall webhook rejected: missing signature headers', [
                'has_id' => $id !== '',
                'has_timestamp' => $timestamp !== '',
                'has_signature' => $signature !== '',
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        if (! $this->recall->verifyWebhookSignature($body, $id, $timestamp, $signature)) {
            Log::warning('Recall webhook rejected: signature verification failed', [
                'webhook_id' => $id,
                'webhook_timestamp' => $timestamp,
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
