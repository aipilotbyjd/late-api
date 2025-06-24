<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignUuid('team_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('provider'); // slack, notion, google_sheets, etc.
            $table->enum('type', ['oauth2', 'api_key', 'basic']);
            $table->string('name');
            $table->jsonb('data'); // Encrypted tokens/secrets
            $table->jsonb('meta')->nullable(); // Non-sensitive info
            $table->jsonb('shared_with')->nullable(); // For selective sharing
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'team_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('credentials');
    }
};
