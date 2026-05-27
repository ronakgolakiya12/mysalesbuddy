<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\CoachingPromptVersion;
use App\Models\NotetakerConfig;
use App\Models\User;
use App\Support\Enums\MeetingScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function createUser(): User
    {
        $name = $this->string('name')->toString();
        $email = $this->string('email')->toString();
        $password = $this->string('password')->toString();

        $user = DB::transaction(function () use ($name, $email, $password): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $firstWord = Str::of($name)->trim()->explode(' ')->first() ?: $name;

            NotetakerConfig::create([
                'user_id' => $user->id,
                'display_name' => $firstWord."'s Assistant",
                'intro_message' => null,
                'default_scope' => MeetingScope::Private,
            ]);

            CoachingPromptVersion::create([
                'user_id' => $user->id,
                'prompt_text' => (string) config('coaching.default_prompt'),
                'is_active' => true,
            ]);

            return $user;
        });

        Auth::guard('web')->login($user, true);
        $this->session()->regenerate();

        return $user;
    }
}
