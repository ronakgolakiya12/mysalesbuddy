<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CoachingAnalysis;
use App\Models\CoachingPromptVersion;
use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Support\Enums\CoachingMode;
use App\Support\Enums\MeetingProvider;
use App\Support\Enums\MeetingScope;
use App\Support\Enums\MeetingStatus;
use Illuminate\Database\Seeder;

class MeetingSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'otto@mysalesbuddy.dev')->firstOrFail();
        $promptVersion = CoachingPromptVersion::where('user_id', $user->id)->active()->first();

        $readyOne = Meeting::create([
            'user_id' => $user->id,
            'recall_bot_id' => 'bot_ready_1',
            'external_meeting_url' => 'https://meet.google.com/abc-defg-hij',
            'title' => 'Acme Corp - Discovery',
            'provider' => MeetingProvider::GoogleMeet,
            'status' => MeetingStatus::Ready,
            'scope' => MeetingScope::Private,
            'scheduled_at' => now()->subDays(3),
            'started_at' => now()->subDays(3),
            'ended_at' => now()->subDays(3)->addMinutes(28),
            'duration_seconds' => 1680,
        ]);
        $this->seedTranscript($readyOne->id);
        $this->seedAnalysis($readyOne->id, $promptVersion?->id, 82);

        $readyTwo = Meeting::create([
            'user_id' => $user->id,
            'recall_bot_id' => 'bot_ready_2',
            'external_meeting_url' => 'https://teams.microsoft.com/l/meetup-join/xyz',
            'title' => 'Globex - Pricing Review',
            'provider' => MeetingProvider::Teams,
            'status' => MeetingStatus::Ready,
            'scope' => MeetingScope::Private,
            'scheduled_at' => now()->subDays(1),
            'started_at' => now()->subDays(1),
            'ended_at' => now()->subDays(1)->addMinutes(42),
            'duration_seconds' => 2520,
        ]);
        $this->seedTranscript($readyTwo->id);
        $this->seedAnalysis($readyTwo->id, $promptVersion?->id, 74);

        Meeting::create([
            'user_id' => $user->id,
            'recall_bot_id' => 'bot_processing_1',
            'external_meeting_url' => 'https://zoom.us/j/123456789',
            'title' => 'Initech - Demo',
            'provider' => MeetingProvider::Zoom,
            'status' => MeetingStatus::Processing,
            'scope' => MeetingScope::Private,
            'scheduled_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(5),
            'duration_seconds' => 3300,
        ]);

        Meeting::create([
            'user_id' => $user->id,
            'recall_bot_id' => null,
            'external_meeting_url' => 'https://meet.google.com/upcoming-call',
            'title' => 'Hooli - Intro',
            'provider' => MeetingProvider::GoogleMeet,
            'status' => MeetingStatus::Scheduled,
            'scope' => MeetingScope::Private,
            'scheduled_at' => now()->addDay(),
        ]);

        Meeting::create([
            'user_id' => $user->id,
            'recall_bot_id' => 'bot_failed_1',
            'external_meeting_url' => 'https://zoom.us/j/999888777',
            'title' => 'Pied Piper - Sync',
            'provider' => MeetingProvider::Zoom,
            'status' => MeetingStatus::Failed,
            'scope' => MeetingScope::Private,
            'scheduled_at' => now()->subDays(2),
            'started_at' => now()->subDays(2),
            'ended_at' => now()->subDays(2)->addMinutes(2),
            'duration_seconds' => 120,
        ]);
    }

    private function seedTranscript(string $meetingId): void
    {
        $segments = [
            ['Rep', 'Thanks for taking the time today. To start, can you tell me a bit about your current workflow?'],
            ['Prospect', 'Sure, we have about thirty reps using a mix of spreadsheets and our CRM, and reporting takes forever.'],
            ['Rep', 'Got it. What does forever look like, in hours per week?'],
            ['Prospect', 'Probably ten to twelve hours from our ops lead, and another five spread across managers.'],
            ['Rep', 'That is significant. If we could compress that to under two hours, what would that unlock for the team?'],
            ['Prospect', 'Honestly, our ops lead could finally work on enablement instead of firefighting reports.'],
        ];

        $offset = 0;
        foreach ($segments as $i => [$speaker, $body]) {
            TranscriptSegment::create([
                'meeting_id' => $meetingId,
                'speaker_label' => $speaker,
                'body' => $body,
                'start_ms' => $offset,
                'end_ms' => $offset + 8000,
            ]);
            $offset += 8500;
            unset($i);
        }
    }

    private function seedAnalysis(string $meetingId, ?string $promptVersionId, int $score): void
    {
        CoachingAnalysis::create([
            'meeting_id' => $meetingId,
            'prompt_version_id' => $promptVersionId,
            'mode' => CoachingMode::TranscriptOnly,
            'overall_score' => $score,
            'talk_time_rep' => 48,
            'talk_time_prospect' => 52,
            'triggered_by' => 'auto',
            'completed_at' => now(),
            'output_json' => [
                'overall_score' => $score,
                'one_liner' => 'Strong discovery with clear quantification of pain and a concrete next step.',
                'rationale' => 'Rep led with discovery, quantified the pain in hours, and locked a follow-up. Decision-maker and budget owner not yet confirmed.',
                'next_step_clarity' => 'clear',
                'next_step_detail' => 'Send recap email and schedule technical deep dive next week.',
                'discovery_quality' => [
                    'pain_uncovered' => true,
                    'impact_quantified' => true,
                    'decision_process_explored' => false,
                    'timeline_confirmed' => true,
                    'missed_areas' => ['budget owner'],
                ],
                'objection_handling' => [
                    'summary' => 'No major objections raised — call was largely cooperative.',
                    'objections' => [],
                ],
                'strengths' => [
                    [
                        'title' => 'Quantified pain',
                        'detail' => 'Translated process drag into hours per week from the ops lead.',
                        'evidence' => [
                            'speaker' => 'Rep',
                            'timestamp_ms' => 180000,
                            'quote' => 'Probably ten to twelve hours from our ops lead.',
                        ],
                    ],
                    [
                        'title' => 'Tied pain to impact',
                        'detail' => 'Linked the lost hours to enablement work the team wanted to ship.',
                        'evidence' => [
                            'speaker' => 'Rep',
                            'timestamp_ms' => 240000,
                            'quote' => 'Our ops lead could finally work on enablement.',
                        ],
                    ],
                ],
                'opportunities' => [
                    [
                        'title' => 'Confirm budget owner',
                        'detail' => 'No explicit mention of who signs off on this category of spend.',
                        'suggestion' => 'Ask "Who else needs to weigh in on a decision like this?" before end of call.',
                        'evidence' => null,
                    ],
                    [
                        'title' => 'Push for a paper trail',
                        'detail' => 'Verbal commitment was strong but no written follow-up was scheduled in the meeting.',
                        'suggestion' => 'Confirm a specific calendar invite during the call, not after.',
                        'evidence' => null,
                    ],
                ],
            ],
        ]);
    }
}
