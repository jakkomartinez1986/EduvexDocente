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
        Schema::table('activity_recoveries', function (Blueprint $table) {
            $table->uuid('client_uid')->nullable()->unique()->after('activity_id');
        });

        Schema::table('exam_recoveries', function (Blueprint $table) {
            $table->uuid('client_uid')->nullable()->unique()->after('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_recoveries', function (Blueprint $table) {
            $table->dropUnique(['client_uid']);
            $table->dropColumn('client_uid');
        });

        Schema::table('activity_recoveries', function (Blueprint $table) {
            $table->dropUnique(['client_uid']);
            $table->dropColumn('client_uid');
        });
    }
};
