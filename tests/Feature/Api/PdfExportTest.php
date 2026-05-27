<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\GeneratePdfExportJob;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_pdf_dispatches_job_for_ready_meeting(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->ready()->create();

        $this->actingAs($user);
        $response = $this->postJson("/api/meetings/{$meeting->id}/export-pdf");

        $response->assertStatus(202);
        Queue::assertPushed(GeneratePdfExportJob::class, function ($job) use ($meeting): bool {
            return $job->meeting->id === $meeting->id;
        });
    }

    public function test_export_pdf_returns_409_when_not_ready(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $meeting = Meeting::factory()->for($user)->create([
            'status' => MeetingStatus::Processing->value,
        ]);

        $this->actingAs($user);
        $response = $this->postJson("/api/meetings/{$meeting->id}/export-pdf");

        $response->assertStatus(409);
        Queue::assertNotPushed(GeneratePdfExportJob::class);
    }

    public function test_export_pdf_rejects_other_users_meeting(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $meeting = Meeting::factory()->for($owner)->ready()->create();

        $this->actingAs($other);
        $response = $this->postJson("/api/meetings/{$meeting->id}/export-pdf");

        $response->assertStatus(403);
        Queue::assertNotPushed(GeneratePdfExportJob::class);
    }
}
