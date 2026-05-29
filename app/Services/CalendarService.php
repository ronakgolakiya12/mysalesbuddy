<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OauthConnection;
use App\Support\Enums\MeetingProvider;
use Carbon\CarbonImmutable;
use Google\Service\Calendar as GoogleCalendar;
use Google\Service\Calendar\Event;

class CalendarService
{
    public function __construct(private readonly GoogleOAuthService $google)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpcomingGoogleEvents(OauthConnection $connection, int $days = 7): array
    {
        $client = $this->google->buildAuthorisedClient($connection);
        $calendar = new GoogleCalendar($client);

        $timeMin = CarbonImmutable::now()->toRfc3339String();
        $timeMax = CarbonImmutable::now()->addDays($days)->toRfc3339String();

        $eventsList = $calendar->events->listEvents('primary', [
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'maxResults' => 50,
        ]);

        $userEmail = strtolower($connection->user->email);
        $mapped = [];

        foreach ($eventsList->getItems() as $event) {
            /** @var Event $event */
            $meetingUrl = $this->extractMeetingUrl($event);

            if ($meetingUrl === null) {
                continue;
            }

            $organiserEmail = $event->getOrganizer()->getEmail();
            $isOrganiser = $userEmail !== '' && strtolower($organiserEmail) === $userEmail;

            $start = $event->getStart();
            $end = $event->getEnd();

            $mapped[] = [
                'id' => (string) $event->getId(),
                'title' => $event->getSummary() ?: 'Untitled event',
                'description' => $event->getDescription(),
                'start_at' => CarbonImmutable::parse(
                    (string) ($start->getDateTime() ?: $start->getDate())
                )->utc()->toIso8601String(),
                'end_at' => CarbonImmutable::parse(
                    (string) ($end->getDateTime() ?: $end->getDate())
                )->utc()->toIso8601String(),
                'meeting_url' => $meetingUrl,
                'provider' => $this->detectProvider($meetingUrl),
                'organiser_email' => $organiserEmail,
                'is_organiser' => $isOrganiser,
            ];
        }

        return $mapped;
    }

    private function extractMeetingUrl(Event $event): ?string
    {
        $hangout = $event->getHangoutLink();
        if ($hangout !== '') {
            return $hangout;
        }

        $description = $event->getDescription();
        if ($description !== '') {
            if (preg_match('#https?://meet\.google\.com/[A-Za-z0-9\-_?=&]+#i', $description, $matches) === 1) {
                return $matches[0];
            }
        }

        return null;
    }

    private function detectProvider(string $url): ?string
    {
        if (stripos($url, 'meet.google.com') !== false) {
            return MeetingProvider::GoogleMeet->value;
        }

        return null;
    }
}
