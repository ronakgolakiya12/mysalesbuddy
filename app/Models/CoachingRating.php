<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Enums\CoachingRating as CoachingRatingEnum;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachingRating extends Model
{
    use HasUuids;

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'coaching_analysis_id',
        'section_key',
        'rating',
    ];

    protected $casts = [
        'rating' => CoachingRatingEnum::class,
        'created_at' => 'datetime',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(CoachingAnalysis::class, 'coaching_analysis_id');
    }
}
