<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Enums\CoachingMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachingAnalysis extends Model
{
    /** @use HasFactory<\Database\Factories\CoachingAnalysisFactory> */
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    const UPDATED_AT = null;

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

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(CoachingPromptVersion::class, 'prompt_version_id');
    }

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
