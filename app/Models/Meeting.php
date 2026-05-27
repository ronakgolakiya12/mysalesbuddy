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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transcriptSegments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class);
    }

    public function coachingAnalyses(): HasMany
    {
        return $this->hasMany(CoachingAnalysis::class);
    }

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
