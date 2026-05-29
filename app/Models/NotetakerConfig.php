<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\StorageService;
use App\Support\Enums\MeetingScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $display_name
 * @property string|null $avatar_path
 * @property string|null $intro_message
 * @property MeetingScope $default_scope
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NotetakerConfig extends Model
{
    /** @use HasFactory<\Database\Factories\NotetakerConfigFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'display_name',
        'avatar_path',
        'intro_message',
        'default_scope',
    ];

    protected $casts = [
        'default_scope' => MeetingScope::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? app(StorageService::class)->getSignedAvatarUrl($this->avatar_path)
            : null;
    }
}
