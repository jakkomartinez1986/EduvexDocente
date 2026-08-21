<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reading_promotion_indicators')->delete();

        DB::table('reading_promotion_indicators')->insert([
            [
                'name' => 'Seleccion autonoma de textos',
                'description' => 'El estudiante selecciona de manera autonoma los textos que desea leer, mostrando preferencias y criterios propios.',
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Interes por la lectura',
                'description' => 'Demuestra interes y motivacion por la lectura, participando con entusiasmo en las actividades propuestas.',
                'order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lectura para el desarrollo afectivo',
                'description' => 'Utiliza la lectura como herramienta para el desarrollo emocional y afectivo, conectando con los textos leidos.',
                'order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lectura para la socializacion y construccion de vinculos',
                'description' => 'Comparte experiencias de lectura con otros, construyendo vinculos sociales a traves del intercambio literario.',
                'order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Comprension lectora',
                'description' => 'Demuestra comprension de los textos leidos, identificando ideas principales, secundarias y elementos clave.',
                'order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Frecuencia de lectura',
                'description' => 'Mantiene un habito regular de lectura, dedicando tiempo de forma consistente a esta actividad.',
                'order' => 6,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('reading_promotion_indicators')->delete();

        DB::table('reading_promotion_indicators')->insert([
            ['name' => 'Comprension lectora', 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Fluidez lectora', 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vocabulario', 'order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Produccion de textos', 'order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Reflexion sobre la lectura', 'order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
};
