<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Support\Enums\MeetingScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotetakerConfigRequest extends FormRequest
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
            'display_name' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
            'intro_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'default_scope' => ['sometimes', 'required', 'string', Rule::in(array_map(static fn (MeetingScope $s) => $s->value, MeetingScope::cases()))],
        ];
    }
}
