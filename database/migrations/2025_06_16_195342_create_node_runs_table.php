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
        Schema::create('node_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('workflow_run_id')->constrained()->onDelete('cascade');
            $table->foreignId('node_id')->constrained()->onDelete('cascade');
            $table->string('status');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('node_runs');
    }
};
