<?php

namespace Tests;

use App\Models\Meeting;
use App\Models\NotetakerConfig;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'http://localhost');
    }

    /**
     * Helper: create a user (with a notetaker config) and act as them.
     */
    protected function actingAsUser(?User $user = null): User
    {
        $user ??= User::factory()->create();

        if (! $user->relationLoaded('notetakerConfig') || $user->notetakerConfig === null) {
            $existing = NotetakerConfig::query()->where('user_id', $user->id)->first();
            if ($existing === null) {
                NotetakerConfig::create([
                    'user_id' => $user->id,
                    'display_name' => "{$user->name}'s Assistant",
                    'default_scope' => 'private',
                ]);
            }
        }

        $this->actingAs($user);

        return $user;
    }

    /**
     * Helper: create a meeting in `ready` state with transcript + coaching.
     */
    protected function createReadyMeeting(?User $user = null): Meeting
    {
        $user ??= User::factory()->create();

        return Meeting::factory()
            ->ready()
            ->withCoaching()
            ->create(['user_id' => $user->id]);
    }

    /**
     * Build a valid set of Standard-Webhooks (Svix-style) signature headers
     * for a given JSON body, configuring the signing secret for the test.
     *
     * @return array<string, string>
     */
    protected function validWebhookHeaders(string $body): array
    {
        $secretKey = 'dGVzdC1zZWNyZXQta2V5LWFiY2RlZmdoaWprbG1ub3A=';
        config(['services.recall.signing_secret' => 'whsec_'.$secretKey]);
        $id = 'msg_test_'.bin2hex(random_bytes(6));
        $ts = (string) time();
        $key = base64_decode($secretKey, true);
        $sig = 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$ts}.{$body}", $key, true));

        return [
            'webhook-id' => $id,
            'webhook-timestamp' => $ts,
            'webhook-signature' => $sig,
            'Content-Type' => 'application/json',
        ];
    }
}
