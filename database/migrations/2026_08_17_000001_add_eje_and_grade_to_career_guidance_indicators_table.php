<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_guidance_indicators', function (Blueprint $table) {
            $table->string('eje', 100)->nullable()->after('name');
            $table->foreignId('grade_id')->nullable()->after('eje')->constrained('grades')->nullOnDelete();
        });

        DB::table('career_guidance_indicators')->delete();

        $grades = DB::table('grades')->pluck('id', 'grade_name')->toArray();

        $indicators = [
            // 8° EGB — Autoconocimiento
            ['eje' => 'Autoconocimiento', 'grade' => '8', 'items' => [
                ['name' => 'Reconoce sus fortalezas y desafios', 'order' => 1],
                ['name' => 'Entiende y habla de sus necesidades y sentimientos', 'order' => 2],
                ['name' => 'Reconoce las necesidades y sentimientos de otras personas', 'order' => 3],
                ['name' => 'Se da cuenta de como su comportamiento afecta a los demas', 'order' => 4],
                ['name' => 'Aprende de sus errores y mira posibilidades de crecimiento', 'order' => 5],
            ]],
            // 9° EGB — Informacion
            ['eje' => 'Informacion', 'grade' => '9', 'items' => [
                ['name' => 'Accede a informacion vocacional de su interesse', 'order' => 1],
                ['name' => 'Identifica opciones de estudio segun sus intereses', 'order' => 2],
                ['name' => 'Analiza requisitos de distintas carreras o profesiones', 'order' => 3],
                ['name' => 'Busca y selecciona informacion relevante para su planificacion', 'order' => 4],
                ['name' => 'Comparte y discute informacion vocacional con sus companeros', 'order' => 5],
            ]],
            // 10° EGB — Toma de decisiones
            ['eje' => 'Toma de decisiones', 'grade' => '10', 'items' => [
                ['name' => 'Define metas educativas y profesionales a corto plazo', 'order' => 1],
                ['name' => 'Evalua alternativas de estudio y trabajo segun sus capacidades', 'order' => 2],
                ['name' => 'Toma decisiones responsables sobre su futuro academico', 'order' => 3],
                ['name' => 'Planifica acciones concretas para alcanzar sus metas', 'order' => 4],
                ['name' => 'Reflexiona sobre las consecuencias de sus decisiones', 'order' => 5],
            ]],
        ];

        foreach ($indicators as $group) {
            $gradeId = $grades[$group['grade']] ?? null;
            foreach ($group['items'] as $item) {
                DB::table('career_guidance_indicators')->insert([
                    'name' => $item['name'],
                    'eje' => $group['eje'],
                    'grade_id' => $gradeId,
                    'order' => $item['order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('career_guidance_indicators', function (Blueprint $table) {
            $table->dropForeign(['grade_id']);
            $table->dropColumn(['eje', 'grade_id']);
        });
    }
};
