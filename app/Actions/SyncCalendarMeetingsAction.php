<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\CalendarNotConnectedException;
use App\Exceptions\CalendarTokenExpiredException;
use App\Models\Meeting;
use App\Models\OauthConnection;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CalendarService;
use App\Support\Enums\AuditEventType;
use App\Support\Enums\MeetingProvider;
use App\Support\Enums\MeetingScope;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\OAuthProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SyncCalendarMeetingsAction
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * @return array{imported: array<int, array<string, mixed>>, existing: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>}
     */
    public function execute(User $user): array
    {
        $connection = OauthConnection::query()
            ->where('user_id', $user->id)
            ->where('provider', OAuthProvider::Google->value)
            ->first();

        if ($connection === null) {
            throw new CalendarNotConnectedException();
        }

        if ($connection->isExpired()) {
            throw new CalendarTokenExpiredException();
        }

        $events = $this->calendar->getUpcomingGoogleEvents($connection, 7);

        $imported = [];
        $existing = [];
        $skipped = [];

        foreach ($events as $event) {
            $meetingUrl = $event['meeting_url'] ?? null;
            $providerString = $event['provider'] ?? null;

            if (! is_string($meetingUrl) || $meetingUrl === '') {
                $skipped[] = [
                    'event_id' => $event['id'] ?? null,
                    'title' => $event['title'] ?? null,
                    'reason' => 'missing_meeting_url',
                ];
                continue;
            }

            $provider = is_string($providerString) ? MeetingProvider::tryFrom($providerString) : null;
            if ($provider !== MeetingProvider::GoogleMeet) {
                $skipped[] = [
                    'event_id' => $event['id'] ?? null,
                    'title' => $event['title'] ?? null,
                    'reason' => 'unsupported_provider',
                ];
                continue;
            }

            $startAt = $this->normaliseStart($event['start_at'] ?? null);
            if ($startAt !== null && $startAt->isPast()) {
                $skipped[] = [
                    'event_id' => $event['id'] ?? null,
                    'title' => $event['title'] ?? null,
                    'reason' => 'event_in_past',
                ];
                continue;
            }

            $duplicate = Meeting::query()
                ->where('user_id', $user->id)
                ->where('external_meeting_url', $meetingUrl)
                ->whereIn('status', [
                    MeetingStatus::Scheduled->value,
                    MeetingStatus::BotJoining->value,
                    MeetingStatus::Recording->value,
                    MeetingStatus::Processing->value,
                ])
                ->first();

            if ($duplicate !== null) {
                $existing[] = [
                    'event_id' => $event['id'] ?? null,
                    'title' => $event['title'] ?? null,
                    'meeting_id' => (string) $duplicate->id,
                    'meeting_url' => $meetingUrl,
                ];
                continue;
            }

            $title = is_string($event['title'] ?? null) && $event['title'] !== ''
                ? (string) $event['title']
                : 'Untitled event';

            $meeting = DB::transaction(function () use ($user, $meetingUrl, $title, $startAt) {
                $meeting = new Meeting([
                    'user_id' => $user->id,
                    'external_meeting_url' => $meetingUrl,
                    'title' => $title,
                    'provider' => MeetingProvider::GoogleMeet,
                    'status' => MeetingStatus::Scheduled,
                    'scope' => MeetingScope::Private,
                    'scheduled_at' => $startAt,
                ]);
                $meeting->save();

                $this->audit->log(
                    user: $user,
                    event: AuditEventType::MeetingCreated,
                    entityType: 'meeting',
                    entityId: (string) $meeting->id,
                    metadata: [
                        'provider' => MeetingProvider::GoogleMeet->value,
                        'scheduled_at' => $startAt?->toIso8601String(),
                        'source' => 'calendar_sync',
                    ]
                );

                $this->audit->log(
                    user: $user,
                    event: AuditEventType::ScopeResolved,
                    entityType: 'meeting',
                    entityId: (string) $meeting->id,
                    metadata: [
                        'scope' => MeetingScope::Private->value,
                        'source' => 'calendar_sync',
                    ]
                );

                return $meeting;
            });

            $imported[] = [
                'event_id' => $event['id'] ?? null,
                'meeting_id' => (string) $meeting->id,
                'title' => $title,
                'meeting_url' => $meetingUrl,
                'scheduled_at' => $startAt?->toIso8601String(),
            ];
        }

        $this->audit->log(
            user: $user,
            event: AuditEventType::CalendarSynced,
            entityType: 'calendar',
            entityId: (string) $connection->id,
            metadata: [
                'imported_count' => count($imported),
                'existing_count' => count($existing),
                'skipped_count' => count($skipped),
            ]
        );

        return [
            'imported' => $imported,
            'existing' => $existing,
            'skipped' => $skipped,
        ];
    }

    private function normaliseStart(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
