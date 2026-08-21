<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
            // 9° EGB — Información
            ['eje' => 'Informacion', 'grade' => '9', 'items' => [
                ['name' => 'Conoce como acceder a informacion necesaria', 'order' => 1],
                ['name' => 'Conoce alternativas relacionadas con opciones de estudios, ocupaciones y planes de carrera', 'order' => 2],
                ['name' => 'Encuentra la solucion a problemas tomando en cuenta otros criterios', 'order' => 3],
                ['name' => 'Comparte y expresa informacion de diferentes areas de conocimiento', 'order' => 4],
                ['name' => 'Explora las posibilidades de sus areas de interes en los ambitos personal, academico, social', 'order' => 5],
            ]],
            // 10° EGB — Toma de decisiones
            ['eje' => 'Toma de decisiones', 'grade' => '10', 'items' => [
                ['name' => 'Expresa motivacion para la toma de decisiones con respecto a su plan de vida', 'order' => 1],
                ['name' => 'Identifica soluciones alternativas', 'order' => 2],
                ['name' => 'Identifica la decision que se debe tomar', 'order' => 3],
                ['name' => 'Evalua las consecuencias de sus decisiones', 'order' => 4],
                ['name' => 'Expresa seguridad y autonomia en la toma de decisiones', 'order' => 5],
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
        DB::table('career_guidance_indicators')->delete();
    }
};
