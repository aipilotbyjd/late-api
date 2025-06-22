<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all teams
        $teams = Team::all();

        if ($teams->isEmpty()) {
            // If no teams exist, run TeamSeeder first
            $this->call([TeamSeeder::class]);
            $teams = Team::all();
        }

        $projects = [
            [
                'id' => (string) Str::uuid(),
                'name' => 'Customer Onboarding',
                'description' => 'Project for managing customer onboarding workflows',
                'team_id' => $teams[0]->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Marketing Campaigns',
                'description' => 'Project for marketing automation workflows',
                'team_id' => $teams[1]->id ?? $teams[0]->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Internal Tools',
                'description' => 'Project for internal tooling and automation',
                'team_id' => $teams[0]->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Product Development',
                'description' => 'Project for core product development',
                'team_id' => $teams[2]->id ?? $teams[0]->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($projects as $project) {
            Project::updateOrCreate(
                ['name' => $project['name']],
                $project
            );
        }
    }
}
