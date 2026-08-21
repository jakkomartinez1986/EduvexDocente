<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_periods', function (Blueprint $table) {
            $table->date('grading_open_date')->nullable()->after('end_date');
            $table->date('grading_close_date')->nullable()->after('grading_open_date');
            $table->boolean('is_supletorio')->default(false)->after('grading_close_date');
        });
    }

    public function down(): void
    {
        Schema::table('academic_periods', function (Blueprint $table) {
            $table->dropColumn(['grading_open_date', 'grading_close_date', 'is_supletorio']);
        });
    }
};
