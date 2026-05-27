<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'otto@mysalesbuddy.dev'],
            [
                'name' => 'Otto',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
