<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Enums\AuditEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_log';

    public $timestamps = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event_type',
        'entity_type',
        'entity_id',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'event_type' => AuditEventType::class,
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('AuditLog is immutable');
        }

        return parent::save($options);
    }
}
