<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('integral_classroom_support_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('integral_classroom_support_indicators')->insert([
            ['name' => 'Autoconocimiento', 'order' => 1],
            ['name' => 'Pensamiento Critico', 'order' => 2],
            ['name' => 'Manejo de Problemas', 'order' => 3],
            ['name' => 'Toma de Decisiones', 'order' => 4],
            ['name' => 'Trabajo en Equipo', 'order' => 5],
            ['name' => 'Empatia', 'order' => 6],
            ['name' => 'Manejo de Conflictos', 'order' => 7],
            ['name' => 'Comunicacion Efectiva/Asertiva', 'order' => 8],
            ['name' => 'Manejo de Emociones y Sentimientos', 'order' => 9],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integral_classroom_support_indicators');
    }
};
