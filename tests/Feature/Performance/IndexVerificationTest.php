<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndexVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function indexesFor(string $table): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Index verification only runs against PostgreSQL.');
        }

        $rows = DB::select(
            "SELECT indexname FROM pg_indexes WHERE schemaname = 'public' AND tablename = ?",
            [$table]
        );

        return array_map(static fn (object $r): string => (string) $r->indexname, $rows);
    }

    public function test_meetings_table_has_critical_indexes(): void
    {
        $indexes = $this->indexesFor('meetings');

        $this->assertContains('meetings_user_id_status_index', $indexes);
        $this->assertContains('meetings_user_id_scheduled_at_index', $indexes);
        $this->assertContains('meetings_user_id_deleted_at_index', $indexes);
        $this->assertContains('meetings_recall_bot_id_unique', $indexes);
    }

    public function test_transcript_segments_has_meeting_index_and_gin_trgm(): void
    {
        $indexes = $this->indexesFor('transcript_segments');

        $this->assertContains('transcript_segments_meeting_id_start_ms_index', $indexes);

        $hasTrgm = in_array('transcript_segments_body_gin', $indexes, true)
            || in_array('transcript_segments_body_trgm_idx', $indexes, true);
        $this->assertTrue($hasTrgm, 'transcript_segments must have a gin/trgm index on body.');
    }

    public function test_notifications_has_user_indexes(): void
    {
        $indexes = $this->indexesFor('notifications');

        $hasUserCreated = in_array('notifications_user_created_idx', $indexes, true);
        $hasUserReadAt = in_array('notifications_user_id_read_at_index', $indexes, true);

        $this->assertTrue($hasUserCreated || $hasUserReadAt, 'notifications must have a user-scoped index.');
    }

    public function test_audit_log_has_required_indexes(): void
    {
        $indexes = $this->indexesFor('audit_log');

        $this->assertContains('audit_log_user_id_event_type_index', $indexes);
        $this->assertContains('audit_log_entity_type_entity_id_index', $indexes);
    }

    public function test_coaching_analyses_has_meeting_index(): void
    {
        $indexes = $this->indexesFor('coaching_analyses');

        $hasMeetingIndex = in_array('coaching_analyses_meeting_id_index', $indexes, true)
            || in_array('coaching_analyses_meeting_created_idx', $indexes, true);

        $this->assertTrue($hasMeetingIndex);
    }
}
