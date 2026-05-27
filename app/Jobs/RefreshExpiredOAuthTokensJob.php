<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OauthConnection;
use App\Services\GoogleOAuthService;
use App\Support\Enums\OAuthProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshExpiredOAuthTokensJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(GoogleOAuthService $google): void
    {
        $threshold = now()->addMinutes(10);

        $connections = OauthConnection::query()
            ->where('provider', OAuthProvider::Google->value)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', $threshold)
            ->get();

        foreach ($connections as $connection) {
            try {
                $tokens = $google->refreshAccessToken($connection);

                $updates = [
                    'access_token' => (string) ($tokens['access_token'] ?? $connection->access_token),
                ];

                if (isset($tokens['refresh_token']) && $tokens['refresh_token'] !== '') {
                    $updates['refresh_token'] = (string) $tokens['refresh_token'];
                }

                if (isset($tokens['expires_in']) && is_numeric($tokens['expires_in'])) {
                    $updates['token_expires_at'] = now()->addSeconds((int) $tokens['expires_in']);
                }

                $connection->fill($updates);
                $connection->save();
            } catch (Throwable $e) {
                Log::warning('Failed to refresh OAuth token.', [
                    'connection_id' => $connection->id,
                    'user_id' => $connection->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
