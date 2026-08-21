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
        Schema::create('grading_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('year_id')->constrained('scolar_years')->cascadeOnDelete();
            $table->decimal('formative_percentage', 5, 2)->default(70);
            $table->decimal('summative_percentage', 5, 2)->default(30);
            $table->decimal('exam_percentage', 5, 2)->default(20);
            $table->decimal('project_percentage', 5, 2)->default(10);
            $table->integer('status')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_schemes');
    }
};
