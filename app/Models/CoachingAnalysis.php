<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Enums\CoachingMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $meeting_id
 * @property string|null $prompt_version_id
 * @property CoachingMode|string $mode
 * @property string|null $deal_context
 * @property int|null $overall_score
 * @property int|null $talk_time_rep
 * @property int|null $talk_time_prospect
 * @property array<string, mixed>|null $output_json
 * @property string $triggered_by
 * @property string|null $provider_used
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property string|null $failure_reason
 * @property Carbon $created_at
 */
class CoachingAnalysis extends Model
{
    /** @use HasFactory<\Database\Factories\CoachingAnalysisFactory> */
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'meeting_id',
        'prompt_version_id',
        'mode',
        'deal_context',
        'overall_score',
        'talk_time_rep',
        'talk_time_prospect',
        'output_json',
        'triggered_by',
        'provider_used',
        'completed_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'mode' => CoachingMode::class,
        'output_json' => 'array',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<CoachingPromptVersion, $this> */
    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(CoachingPromptVersion::class, 'prompt_version_id');
    }

    /** @return HasMany<CoachingRating, $this> */
    public function ratings(): HasMany
    {
        return $this->hasMany(CoachingRating::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function structuredOutput(): array
    {
        return $this->output_json ?? [];
    }
}
