<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'paused', 'draft', 'error']);
            $table->enum('trigger_type', ['webhook', 'polling', 'schedule', 'manual'])->nullable();
            $table->string('webhook_token', 64)->unique()->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('cron_expression')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->uuid('active_version_id')->nullable()->index();
            $table->foreign('active_version_id')->references('id')->on('workflow_versions')->nullOnDelete();
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
