<?php

declare(strict_types=1);

namespace App\Http\Requests\Meeting;

use App\Exceptions\UnsupportedMeetingProviderException;
use App\Rules\AllowedMeetingUrl;
use App\Support\Enums\MeetingProvider;
use App\Support\Enums\MeetingScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_meeting_url' => ['required', 'string', 'url', 'max:2048', new AllowedMeetingUrl()],
            'title' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'scope' => ['nullable', Rule::in([MeetingScope::Private->value, MeetingScope::Team->value])],
        ];
    }

    public function detectProvider(): MeetingProvider
    {
        $url = (string) $this->input('external_meeting_url', '');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === 'meet.google.com' || str_ends_with($host, '.meet.google.com')) {
            return MeetingProvider::GoogleMeet;
        }

        throw new UnsupportedMeetingProviderException();
    }

    public function scopeValue(): MeetingScope
    {
        $value = $this->input('scope');
        if ($value === MeetingScope::Team->value) {
            return MeetingScope::Team;
        }

        return MeetingScope::Private;
    }
}
