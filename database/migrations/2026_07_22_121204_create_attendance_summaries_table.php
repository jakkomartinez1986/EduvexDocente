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
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('grade_id')->constrained('grades');
            $table->foreignId('trimester_id')->constrained('academic_periods');
            $table->foreignId('year_id')->nullable()->constrained('scolar_years')->nullOnDelete();
            $table->integer('total_classes')->default(0);
            $table->integer('present_count')->default(0);
            $table->integer('late_count')->default(0);
            $table->integer('unjustified_count')->default(0);
            $table->integer('justified_count')->default(0);
            $table->integer('abandonment_count')->default(0);
            $table->integer('permission_count')->default(0);
            $table->softDeletes();
            $table->timestamp('last_updated');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_summaries');
    }
};
