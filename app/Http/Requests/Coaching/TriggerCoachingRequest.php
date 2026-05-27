<?php

declare(strict_types=1);

namespace App\Http\Requests\Coaching;

use App\Support\Enums\CoachingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TriggerCoachingRequest extends FormRequest
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
            'mode' => ['required', Rule::in([
                CoachingMode::TranscriptOnly->value,
                CoachingMode::DiscoveryAware->value,
            ])],
            'deal_context' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function modeValue(): CoachingMode
    {
        return CoachingMode::from((string) $this->input('mode'));
    }

    public function dealContext(): ?string
    {
        $value = $this->input('deal_context');
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
