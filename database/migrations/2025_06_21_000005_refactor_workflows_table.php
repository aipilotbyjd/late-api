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
        Schema::table('workflows', function (Blueprint $table) {
            // Add active_version_id column
            $table->foreignId('active_version_id')->nullable()->constrained('workflow_versions')->nullOnDelete();

            // Drop redundant columns
            $table->dropColumn(['workflow_json', 'version', 'settings']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            // Add the columns back
            $table->json('workflow_json')->nullable();
            $table->string('version', 50)->default('1.0.0');
            $table->json('settings')->nullable();

            // Drop the foreign key and column
            $table->dropForeign(['active_version_id']);
            $table->dropColumn('active_version_id');
        });
    }
};
