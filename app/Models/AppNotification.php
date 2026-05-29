<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property array<string, mixed>|null $payload_json
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
class AppNotification extends Model
{
    /** @use HasFactory<\Database\Factories\AppNotificationFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'notifications';

    public $timestamps = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'type',
        'payload_json',
        'read_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        $this->read_at = now();
        $this->save();
    }
}
