<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        // Create teams only if they don't exist
        $teams = [
            [
                'name' => 'Development Team',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Marketing Team',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Operations',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($teams as $team) {
            $existingTeam = Team::where('name', $team['name'])->first();
            if (!$existingTeam) {
                $team['id'] = (string) Str::uuid();
                Team::create($team);
            }
        }
    }
}
