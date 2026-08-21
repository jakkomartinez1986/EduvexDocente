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
        Schema::create('career_guidance_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('career_guidance_indicators')->insert([
            ['name' => 'Acceso a informacion vocacional', 'order' => 1],
            ['name' => 'Opciones de estudio', 'order' => 2],
            ['name' => 'Resolucion de problemas', 'order' => 3],
            ['name' => 'Comunicacion de ideas', 'order' => 4],
            ['name' => 'Exploracion de intereses', 'order' => 5],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('career_guidance_indicators');
    }
};
