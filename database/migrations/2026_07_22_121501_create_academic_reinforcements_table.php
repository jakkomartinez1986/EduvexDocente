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
        Schema::create('academic_reinforcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('grade_id')->constrained('grades')->onDelete('cascade');
            $table->foreignId('trimester_id')->constrained('academic_periods')->onDelete('cascade');
            $table->foreignId('year_id')->constrained('scolar_years')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->tinyInteger('reinforcement_number')->comment('1, 2 , 3 or 4');
            $table->decimal('grade', 5, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('type'); // formativas, sumativas
            $table->date('date')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'subject_id', 'grade_id', 'trimester_id', 'year_id', 'reinforcement_number'], 'academic_reinforcements_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_reinforcements');
    }
};
