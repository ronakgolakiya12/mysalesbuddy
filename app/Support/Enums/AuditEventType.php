<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum AuditEventType: string
{
    case UserLogin = 'user.login';
    case UserLogout = 'user.logout';
    case MeetingCreated = 'meeting.created';
    case MeetingDeleted = 'meeting.deleted';
    case MeetingDispatchCancelled = 'meeting.dispatch_cancelled';
    case MeetingBotDispatched = 'meeting.bot_dispatched';
    case TranscriptProcessed = 'transcript.processed';
    case CoachingTriggered = 'coaching.triggered';
    case CoachingCompleted = 'coaching.completed';
    case CoachingFailed = 'coaching.failed';
    case CoachingRated = 'coaching.rated';
    case PromptUpdated = 'prompt.updated';
    case PromptVersionCreated = 'prompt.version_created';
    case OauthConnected = 'oauth.connected';
    case PdfExported = 'pdf.exported';
    case CalendarSynced = 'calendar.synced';
    case ScopeResolved = 'scope.resolved';
}
