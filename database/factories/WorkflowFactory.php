<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workflow>
 */
class WorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => \App\Models\Project::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => 'draft',
            'workflow_json' => [
                'nodes' => [],
                'connections' => []
            ],
            'trigger_type' => 'webhook',
            'webhook_token' => bin2hex(random_bytes(32)),
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the workflow is active.
     */
    public function active()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the workflow is paused.
     */
    public function paused()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
        ]);
    }

    /**
     * Indicate that the workflow is public.
     */
    public function public()
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }
}
