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
        Schema::create('workflow_run_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained();
            $table->string('node_id');
            $table->enum('status', ['pending', 'running', 'success', 'failed']);
            $table->json('input_data');
            $table->json('output_data');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_run_logs');
    }
};
