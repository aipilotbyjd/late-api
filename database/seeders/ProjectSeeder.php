<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Seeder;

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
                'name' => 'Customer Onboarding',
                'description' => 'Project for managing customer onboarding workflows',
                'team_id' => $teams[0]->id,
                'is_active' => true,
            ],
            [
                'name' => 'Marketing Campaigns',
                'description' => 'Project for managing marketing campaigns',
                'team_id' => $teams[1]->id ?? $teams[0]->id,
                'is_active' => true,
            ],
            [
                'name' => 'Internal Tools',
                'description' => 'Project for internal tooling and automation',
                'team_id' => $teams[0]->id,
                'is_active' => true,
            ],
            [
                'name' => 'Product Development',
                'description' => 'Project for core product development',
                'team_id' => $teams[2]->id ?? $teams[0]->id,
                'is_active' => true,
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
