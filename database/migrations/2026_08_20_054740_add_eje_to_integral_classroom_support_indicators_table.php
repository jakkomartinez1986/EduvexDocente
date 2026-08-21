<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integral_classroom_support_indicators', function (Blueprint $table) {
            $table->string('eje', 100)->nullable()->after('name');
        });

        DB::table('integral_classroom_support_indicators')->update(['eje' => 'Habilidades Cognitivas']);
        DB::table('integral_classroom_support_indicators')->whereIn('name', [
            'Manejo de Conflictos',
            'Trabajo en Equipo',
            'Comunicacion Efectiva/Asertiva',
            'Empatia',
        ])->update(['eje' => 'Habilidades Sociales']);
        DB::table('integral_classroom_support_indicators')->where('name', 'Manejo de Emociones y Sentimientos')
            ->update(['eje' => 'Habilidades Emocionales']);
    }

    public function down(): void
    {
        Schema::table('integral_classroom_support_indicators', function (Blueprint $table) {
            $table->dropColumn('eje');
        });
    }
};
