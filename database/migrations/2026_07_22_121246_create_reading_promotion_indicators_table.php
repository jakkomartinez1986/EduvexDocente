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
        Schema::create('reading_promotion_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('reading_promotion_indicators')->insert([
            ['name' => 'Comprension lectora', 'order' => 1],
            ['name' => 'Fluidez lectora', 'order' => 2],
            ['name' => 'Vocabulario', 'order' => 3],
            ['name' => 'Produccion de textos', 'order' => 4],
            ['name' => 'Reflexion sobre la lectura', 'order' => 5],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_promotion_indicators');
    }
};
