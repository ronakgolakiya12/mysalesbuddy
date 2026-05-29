<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\LogFailedAuthAttempt;
use App\Models\AppNotification;
use App\Models\CoachingAnalysis;
use App\Models\Meeting;
use App\Policies\AppNotificationPolicy;
use App\Policies\CoachingAnalysisPolicy;
use App\Policies\MeetingPolicy;
use App\Services\Ai\AiServiceFactory;
use App\Services\Ai\AiServiceInterface;
use App\Services\RecallAiService;
use Gemini as GeminiFactory;
use Gemini\Client as GeminiClient;
use GuzzleHttp\Client;
use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use OpenAI as OpenAIFactory;
use OpenAI\Client as OpenAiClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RecallAiService::class, function (): RecallAiService {
            $client = new Client([
                'base_uri' => (string) config('services.recall.base_url', 'https://api.recall.ai/api/v1/'),
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Token '.(string) config('services.recall.api_key'),
                    'Accept' => 'application/json',
                ],
            ]);

            return new RecallAiService($client);
        });

        $this->app->singleton(OpenAiClient::class, function (): OpenAiClient {
            $timeout = (int) config('services.openai.timeout', 55);
            $httpClient = new Client([
                'timeout' => $timeout,
                'connect_timeout' => 10,
            ]);

            return OpenAIFactory::factory()
                ->withApiKey((string) config('services.openai.api_key'))
                ->withHttpClient($httpClient)
                ->make();
        });

        $this->app->singleton(GeminiClient::class, function (): GeminiClient {
            return GeminiFactory::client((string) config('services.gemini.api_key'));
        });

        // Resolve coaching analysis to the provider currently selected by
        // AI_PROVIDER. Bound (not singleton) so changing the env + clearing
        // config picks up the new provider on the next request.
        $this->app->bind(AiServiceInterface::class, function (): AiServiceInterface {
            return AiServiceFactory::make();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Meeting::class, MeetingPolicy::class);
        Gate::policy(CoachingAnalysis::class, CoachingAnalysisPolicy::class);
        Gate::policy(AppNotification::class, AppNotificationPolicy::class);

        Event::listen(Failed::class, [LogFailedAuthAttempt::class, 'handle']);

        Model::preventLazyLoading(! $this->app->environment('production'));
    }
}
