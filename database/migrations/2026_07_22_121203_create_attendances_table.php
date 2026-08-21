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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_observation_id')->nullable()->constrained('class_observations')->nullOnDelete();
            $table->foreignId('class_schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->foreignId('calendarday_id')->constrained('calendar_days');
            $table->foreignId('year_id')->nullable()->constrained('scolar_years')->nullOnDelete();
            $table->foreignId('tutor_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students');
            $table->date('date');
            $table->char('status', 2)->nullable();
            $table->time('arrival_time')->nullable();
            $table->text('justification')->nullable();
            $table->string('justification_file_path')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->json('notification_data')->nullable();
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('student_id');
            $table->index('recorded_by');
            $table->index(['student_id', 'date']);
            $table->index(['class_schedule_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
