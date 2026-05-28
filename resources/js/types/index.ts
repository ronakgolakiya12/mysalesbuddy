export const enum MeetingStatus {
    Scheduled = 'scheduled',
    BotJoining = 'bot_joining',
    Recording = 'recording',
    Processing = 'processing',
    Ready = 'ready',
    Failed = 'failed',
    Cancelled = 'cancelled',
    Delayed = 'delayed',
}

export const enum MeetingProvider {
    GoogleMeet = 'google_meet',
    Teams = 'teams',
    Zoom = 'zoom',
}

export const enum CoachingMode {
    TranscriptOnly = 'transcript_only',
    DiscoveryAware = 'discovery_aware',
}

export type MeetingScope = 'private' | 'team';

export interface NotetakerConfig {
    id: string;
    user_id: string;
    display_name: string;
    avatar_path: string | null;
    avatar_url: string | null;
    intro_message: string | null;
    default_scope: MeetingScope;
    created_at: string;
    updated_at: string;
}

export interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
    has_google_calendar: boolean;
    has_microsoft_calendar: boolean;
    notetaker_config: NotetakerConfig | null;
    created_at: string;
    updated_at: string;
}

export interface Meeting {
    id: string;
    user_id: string;
    external_meeting_url: string;
    title: string | null;
    provider: MeetingProvider;
    status: MeetingStatus;
    scope: MeetingScope;
    scheduled_at: string | null;
    started_at: string | null;
    ended_at: string | null;
    duration_seconds: number | null;
    duration_formatted?: string | null;
    transcript_segments?: TranscriptSegment[];
    latest_coaching_analysis?: {
        id: string;
        overall_score: number | null;
        completed_at: string | null;
        created_at: string | null;
    } | null;
    created_at: string;
    updated_at: string;
}

export interface TranscriptSegment {
    id: string;
    speaker_label: string;
    body: string;
    start_ms: number;
    end_ms: number;
}

export interface PaginationMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface CoachingEvidence {
    timestamp_ms: number;
    quote: string;
}

export interface CoachingStrength {
    title: string;
    detail: string;
    evidence: CoachingEvidence | null;
}

export interface CoachingOpportunity {
    title: string;
    detail: string;
    suggestion: string;
    evidence: CoachingEvidence | null;
}

export interface DiscoveryQuality {
    pain_uncovered: boolean;
    impact_quantified: boolean;
    decision_process_explored: boolean;
    timeline_confirmed: boolean;
    missed_areas: string[];
}

export interface ObjectionItem {
    objection: string;
    response_summary: string;
    resolved: boolean;
    evidence: CoachingEvidence | null;
}

export interface ObjectionHandling {
    summary: string;
    objections: ObjectionItem[];
}

export type NextStepClarity = 'clear' | 'vague' | 'missing' | null;

export interface CoachingAnalysisOutput {
    one_liner: string;
    rationale: string;
    next_step_clarity: NextStepClarity;
    next_step_detail: string;
    discovery_quality: DiscoveryQuality;
    objection_handling: ObjectionHandling;
    strengths: CoachingStrength[];
    opportunities: CoachingOpportunity[];
}

export interface CoachingRating {
    id: string;
    coaching_analysis_id: string;
    section_key: string;
    rating: 'useful' | 'not_useful';
    created_at: string;
    updated_at?: string;
}

export interface CoachingAnalysis {
    id: string;
    meeting_id: string;
    prompt_version_id: string | null;
    mode: CoachingMode;
    deal_context: string | null;
    overall_score: number | null;
    talk_time_rep: number | null;
    talk_time_prospect: number | null;
    output_json: CoachingAnalysisOutput | null;
    triggered_by: 'auto' | 'manual';
    status: 'pending' | 'completed' | 'failed';
    completed_at: string | null;
    failed_at: string | null;
    failure_reason: string | null;
    created_at: string;
    ratings: CoachingRating[];
}

export interface CoachingPromptVersion {
    id: string;
    prompt_text: string;
    is_active: boolean;
    created_at: string;
}

export interface OauthConnection {
    id: string;
    user_id: string;
    provider: 'google' | 'microsoft';
    token_expires_at: string | null;
    scopes: string[];
    created_at: string;
    updated_at: string;
}

export type NotificationType =
    | 'bot_blocked'
    | 'transcript_failed'
    | 'transcript_delayed'
    | 'coaching_ready'
    | 'pdf_ready';

export interface NotificationPayload {
    meeting_id?: string;
    meeting_title?: string | null;
    overall_score?: number | null;
    download_url?: string;
    blocked_at?: string;
    failed_at?: string;
    processing_since?: string;
    analysis_id?: string;
}

export interface AppNotification {
    id: string;
    user_id: string;
    type: NotificationType;
    payload: NotificationPayload;
    read_at: string | null;
    created_at: string;
}

export interface NotificationChannelPreference {
    in_app: boolean;
    email: boolean;
}

export interface NotificationPreferences {
    bot_blocked: NotificationChannelPreference;
    transcript_failed: NotificationChannelPreference;
    transcript_delayed: NotificationChannelPreference;
    coaching_ready: NotificationChannelPreference;
    pdf_ready: NotificationChannelPreference;
}

export type ValidationErrors = Record<string, string[]>;

export interface ApiSuccessResponse<T> {
    data: T;
}

export interface ApiPaginatedMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface ApiPaginatedResponse<T> {
    data: T[];
    meta: ApiPaginatedMeta;
}

export interface ApiErrorResponse {
    message: string;
    errors?: ValidationErrors;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
