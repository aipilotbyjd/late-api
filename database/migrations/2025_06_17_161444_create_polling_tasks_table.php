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
        Schema::create('polling_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_id')->index();
            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
            $table->timestamp('last_checked_at');
            $table->timestamp('next_run_at');
            $table->integer('interval_minutes');
            $table->enum('status', ['pending', 'running', 'error']);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('polling_tasks');
    }
};
