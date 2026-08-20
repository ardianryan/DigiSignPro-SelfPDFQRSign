<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Default App Settings
        AppSetting::create([
            'app_name' => 'DigiSign Pro',
            'maintenance_mode' => false,
            'registration_open' => true,
        ]);

        // 2. Seed Admin User
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'position' => 'IT Administrator',
            'api_key' => User::generateApiKey(),
            'api_key_created_at' => now(),
        ]);

        // 3. Seed Regular User
        User::create([
            'name' => 'Demo User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'position' => 'Staff',
            'api_key' => User::generateApiKey(),
            'api_key_created_at' => now(),
        ]);
    }
}
