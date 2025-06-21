<?php

namespace Database\Seeders;

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
        // Create a test user if none exists
        $user = User::firstOrCreate(
            ['email' => 'jaydeepdhrangiya@gmail.com'],
            [
                'first_name' => 'Jaydeep',
                'last_name' => 'Dhrangiya',
                'password' => Hash::make('Jaydeep@123'),
                'email_verified_at' => now(),
            ]
        );

        // Run seeders in order
        $this->call([
            TeamSeeder::class,
            ProjectSeeder::class,
            // Add other seeders here
        ]);
    }
}
