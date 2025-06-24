<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('credential_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('credential_id')->constrained()->cascadeOnDelete();
            $table->string('used_by_type'); // Workflow, WorkflowVersion, IntegrationTest, etc.
            $table->uuid('used_by_id');
            $table->timestamps();

            $table->index(['used_by_type', 'used_by_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('credential_usages');
    }
};
