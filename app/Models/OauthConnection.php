<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedString;
use App\Support\Enums\OAuthProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OauthConnection extends Model
{
    /** @use HasFactory<\Database\Factories\OauthConnectionFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'provider',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
    ];

    protected $casts = [
        'access_token' => EncryptedString::class,
        'refresh_token' => EncryptedString::class,
        'token_expires_at' => 'datetime',
        'provider' => OAuthProvider::class,
        'scopes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function expiresWithin(int $minutes): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->lte(now()->addMinutes($minutes));
    }

    public function scopeForProvider(Builder $query, OAuthProvider|string $provider): Builder
    {
        return $query->where('provider', $provider instanceof OAuthProvider ? $provider->value : $provider);
    }

    public function scopeGoogle(Builder $query): Builder
    {
        return $query->where('provider', OAuthProvider::Google->value);
    }
}
