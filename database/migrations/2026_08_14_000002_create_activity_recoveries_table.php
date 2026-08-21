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
        Schema::create('activity_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('year_id')->nullable()->constrained('scolar_years')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1)->comment('Numero de intento de recuperacion');
            $table->decimal('original_grade', 5, 2)->comment('Nota inicial que se recupera');
            $table->decimal('recovery_grade', 5, 2)->comment('Nota obtenida en la recuperacion');
            $table->string('update_method', 20)->comment('average | highest');
            $table->decimal('final_grade', 5, 2)->nullable()->comment('Nota resultante al aplicar el metodo');
            $table->boolean('is_applied')->default(false)->comment('Si se actualizo en el libro de calificaciones');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['activity_id', 'student_id']);
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_recoveries');
    }
};
