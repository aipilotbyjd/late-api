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
        Schema::create('integration_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained();
            $table->string('name');
            $table->string('display_name');
            $table->text('description');
            $table->json('sample_config');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_triggers');
    }
};
