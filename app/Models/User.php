<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon|null $email_verified_at
 * @property array<string, array{in_app: bool, email: bool}>|null $notification_preferences
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function defaultNotificationPreferences(): array
    {
        return [
            'bot_blocked' => ['in_app' => true, 'email' => true],
            'transcript_failed' => ['in_app' => true, 'email' => true],
            'transcript_delayed' => ['in_app' => true, 'email' => false],
            'coaching_ready' => ['in_app' => true, 'email' => false],
            'pdf_ready' => ['in_app' => true, 'email' => true],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getNotificationPreference(string $type): array
    {
        $defaults = $this->defaultNotificationPreferences();
        $stored = $this->notification_preferences ?? [];

        $default = $defaults[$type] ?? ['in_app' => true, 'email' => false];
        $value = $stored[$type] ?? [];

        return [
            'in_app' => array_key_exists('in_app', $value) ? (bool) $value['in_app'] : $default['in_app'],
            'email' => array_key_exists('email', $value) ? (bool) $value['email'] : $default['email'],
        ];
    }

    public function prefersEmail(string $type): bool
    {
        return $this->getNotificationPreference($type)['email'];
    }

    public function prefersInApp(string $type): bool
    {
        return $this->getNotificationPreference($type)['in_app'];
    }

    /** @return HasOne<NotetakerConfig, $this> */
    public function notetakerConfig(): HasOne
    {
        return $this->hasOne(NotetakerConfig::class);
    }

    /** @return HasMany<OauthConnection, $this> */
    public function oauthConnections(): HasMany
    {
        return $this->hasMany(OauthConnection::class);
    }

    /** @return HasMany<Meeting, $this> */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /** @return HasMany<AppNotification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** @return HasMany<CoachingPromptVersion, $this> */
    public function coachingPromptVersions(): HasMany
    {
        return $this->hasMany(CoachingPromptVersion::class);
    }
}
