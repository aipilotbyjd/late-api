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
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workflow_id')->constrained()->onDelete('cascade');
            $table->string('status', 20);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->integer('execution_time')->nullable()->comment('Execution time in milliseconds');
            $table->text('error')->nullable();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('trigger_type', 20);
            $table->json('trigger_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['workflow_id', 'status']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_executions');
    }
};
