<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('oauth_states', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('state_token');
            $table->text('redirect_uri');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['state_token']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('oauth_states');
    }
};
