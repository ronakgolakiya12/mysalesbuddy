<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferencesController extends Controller
{
    use ApiResponses;

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $defaults = $user->defaultNotificationPreferences();
        $stored = is_array($user->notification_preferences) ? $user->notification_preferences : [];

        $merged = [];
        foreach ($defaults as $key => $default) {
            $merged[$key] = $user->getNotificationPreference($key);
            unset($default);
        }

        unset($stored);

        return $this->success(['preferences' => $merged]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $allowedKeys = array_keys($user->defaultNotificationPreferences());

        $rules = [
            'preferences' => ['required', 'array'],
        ];
        foreach ($allowedKeys as $key) {
            $rules["preferences.{$key}"] = ['sometimes', 'array'];
            $rules["preferences.{$key}.in_app"] = ['sometimes', 'boolean'];
            $rules["preferences.{$key}.email"] = ['sometimes', 'boolean'];
        }

        $validated = $request->validate($rules);

        /** @var array<string, array<string, bool>> $incoming */
        $incoming = $validated['preferences'] ?? [];

        $existing = is_array($user->notification_preferences) ? $user->notification_preferences : [];

        $filtered = [];
        foreach ($allowedKeys as $key) {
            $current = $existing[$key] ?? $user->defaultNotificationPreferences()[$key];
            $incomingPref = $incoming[$key] ?? null;
            if (! is_array($incomingPref)) {
                $filtered[$key] = $current;

                continue;
            }
            $filtered[$key] = [
                'in_app' => array_key_exists('in_app', $incomingPref)
                    ? (bool) $incomingPref['in_app']
                    : (bool) ($current['in_app'] ?? true),
                'email' => array_key_exists('email', $incomingPref)
                    ? (bool) $incomingPref['email']
                    : (bool) ($current['email'] ?? false),
            ];
        }

        $user->notification_preferences = $filtered;
        $user->save();

        return $this->success(['preferences' => $filtered]);
    }
}
