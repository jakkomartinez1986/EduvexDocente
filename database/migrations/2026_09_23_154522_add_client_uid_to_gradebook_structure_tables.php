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
        Schema::table('assessment_blocks', function (Blueprint $table) {
            $table->uuid('client_uid')->nullable()->unique()->after('is_active');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->uuid('client_uid')->nullable()->unique()->after('assessment_block_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropUnique(['client_uid']);
            $table->dropColumn('client_uid');
        });

        Schema::table('assessment_blocks', function (Blueprint $table) {
            $table->dropUnique(['client_uid']);
            $table->dropColumn('client_uid');
        });
    }
};
