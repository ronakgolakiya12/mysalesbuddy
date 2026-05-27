<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NotetakerConfig;
use App\Models\User;
use App\Support\Enums\MeetingScope;
use Illuminate\Database\Seeder;

class NotetakerConfigSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'otto@mysalesbuddy.dev')->firstOrFail();

        NotetakerConfig::updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => "Otto's Assistant",
                'default_scope' => MeetingScope::Private,
            ],
        );
    }
}
