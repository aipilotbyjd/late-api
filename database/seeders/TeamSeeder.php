<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user to be the team owner
        $user = User::first();
        
        if (!$user) {
            // If no users exist, create one
            $user = User::firstOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'first_name' => 'Admin',
                    'last_name' => 'User',
                    'password' => bcrypt('password'),
                    'email_verified_at' => now(),
                ]
            );
        }

        // Create teams
        $teams = [
            [
                'name' => 'Development Team',
                'created_by' => $user->id,
            ],
            [
                'name' => 'Marketing Team',
                'created_by' => $user->id,
            ],
            [
                'name' => 'Operations',
                'created_by' => $user->id,
            ],
        ];

        foreach ($teams as $team) {
            Team::updateOrCreate(
                ['name' => $team['name']],
                $team
            );
        }
    }
}
