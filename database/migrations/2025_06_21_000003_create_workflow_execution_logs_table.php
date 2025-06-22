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
        Schema::create('workflow_execution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_execution_id')->constrained()->onDelete('cascade');
            $table->string('node_id')->nullable();
            $table->string('node_name')->nullable();
            $table->string('node_type')->nullable();
            $table->string('level', 20);
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamps();
            
            $table->index(['workflow_execution_id', 'node_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_execution_logs');
    }
};
