<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->json('workflow_json')->nullable();
            $table->string('trigger_type')->default('manual');
            $table->string('webhook_token', 100)->unique()->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('cron_expression')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('version', 50)->default('1.0.0');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
