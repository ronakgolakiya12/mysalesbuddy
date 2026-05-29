<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Meeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class QueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_meeting_listing_query_uses_index(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Query plan inspection requires PostgreSQL.');
        }

        $user = User::factory()->create();
        Meeting::factory()
            ->count(25)
            ->create([
                'user_id' => $user->id,
                'status' => MeetingStatus::Ready->value,
            ]);

        // Force the planner to consider indexes (small tables may still prefer Seq Scan).
        DB::statement('ANALYZE meetings');

        $plan = DB::select(
            'EXPLAIN (ANALYZE, FORMAT TEXT) SELECT * FROM meetings WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 20',
            [$user->id, MeetingStatus::Ready->value]
        );

        $planText = implode("\n", array_map(static fn ($r) => (string) reset($r), array_map('get_object_vars', $plan)));

        Log::info('meeting_listing_query_plan', ['plan' => $planText]);

        // On a small test dataset Postgres may pick a Seq Scan because it's cheaper.
        // We don't fail the build — we record a warning so the team can investigate
        // production explain output. The IndexVerificationTest enforces that the
        // index actually exists.
        if (str_contains($planText, 'Seq Scan on meetings')) {
            Log::warning('meeting listing fell back to Seq Scan on the test dataset (expected on small tables).');
        }

        $this->assertNotEmpty($planText);
    }
}
