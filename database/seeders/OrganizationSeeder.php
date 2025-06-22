<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationSeeder extends Seeder
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

        $organizations = [
            [
                'name' => 'Customer Onboarding',
                'description' => 'Organization for managing customer onboarding workflows',
                'team_id' => $teams[0]->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Marketing Campaigns',
                'description' => 'Organization for marketing automation workflows',
                'team_id' => $teams[1]->id ?? $teams[0]->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Internal Tools',
                'description' => 'Organization for internal tooling and automation',
                'team_id' => $teams[0]->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Product Development',
                'description' => 'Organization for core product development',
                'team_id' => $teams[2]->id ?? $teams[0]->id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($organizations as $organization) {
            $existingOrganization = Organization::where('name', $organization['name'])->first();
            if (!$existingOrganization) {
                $organization['id'] = (string) Str::uuid();
                Organization::create($organization);
            }
        }
    }
}
