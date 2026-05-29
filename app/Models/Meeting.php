<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Enums\MeetingProvider;
use App\Support\Enums\MeetingScope;
use App\Support\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $recall_bot_id
 * @property string $external_meeting_url
 * @property string|null $title
 * @property MeetingProvider $provider
 * @property MeetingStatus $status
 * @property MeetingScope $scope
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read CoachingAnalysis|null $latestCoachingAnalysis
 */
class Meeting extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'recall_bot_id',
        'external_meeting_url',
        'title',
        'provider',
        'status',
        'scope',
        'scheduled_at',
        'started_at',
        'ended_at',
        'duration_seconds',
    ];

    protected $casts = [
        'status' => MeetingStatus::class,
        'provider' => MeetingProvider::class,
        'scope' => MeetingScope::class,
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TranscriptSegment, $this> */
    public function transcriptSegments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class);
    }

    /** @return HasMany<CoachingAnalysis, $this> */
    public function coachingAnalyses(): HasMany
    {
        return $this->hasMany(CoachingAnalysis::class);
    }

    /** @return HasOne<CoachingAnalysis, $this> */
    public function latestCoachingAnalysis(): HasOne
    {
        return $this->hasOne(CoachingAnalysis::class)
            ->orderByDesc('created_at');
    }

    public function durationFormatted(): ?string
    {
        if ($this->duration_seconds === null) {
            return null;
        }

        $seconds = (int) $this->duration_seconds;
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    public function isProcessable(): bool
    {
        return $this->status->isTerminal();
    }
}
