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
        Schema::create('integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('team_id')->index();
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description');
            $table->string('logo_url');
            $table->enum('auth_type', ['none', 'api_key', 'oauth2', 'basic']);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
