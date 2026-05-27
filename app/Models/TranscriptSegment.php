<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptSegment extends Model
{
    /** @use HasFactory<\Database\Factories\TranscriptSegmentFactory> */
    use HasFactory;
    use HasUuids;

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'meeting_id',
        'speaker_label',
        'body',
        'start_ms',
        'end_ms',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'start_ms' => 'integer',
        'end_ms' => 'integer',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        if (blank($keyword)) {
            return $query;
        }

        return $query->where('body', 'ILIKE', '%'.$keyword.'%');
    }
}
