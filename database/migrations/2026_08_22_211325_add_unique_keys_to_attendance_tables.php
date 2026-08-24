<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * H-06: índice único contra carreras de sincronización concurrente.
     *
     * En `attendances` es un índice único PARCIAL (WHERE deleted_at IS NULL):
     * solo una fila activa por horario/estudiante/día, mientras que los
     * tombstones del soft delete nunca bloquean re-inserciones ni entre sí.
     * La sintaxis es válida en PostgreSQL y SQLite.
     */
    public function up(): void
    {
        DB::statement('DELETE FROM attendances WHERE deleted_at IS NULL AND id NOT IN (SELECT MAX(id) FROM attendances WHERE deleted_at IS NULL GROUP BY class_schedule_id, student_id, date)');

        DB::statement('CREATE UNIQUE INDEX attendances_schedule_student_date_unique ON attendances (class_schedule_id, student_id, date) WHERE deleted_at IS NULL');

        DB::statement('DELETE FROM class_observations WHERE id NOT IN (SELECT MAX(id) FROM class_observations GROUP BY class_schedule_id, observation_date)');

        Schema::table('class_observations', function (Blueprint $table): void {
            $table->unique(['class_schedule_id', 'observation_date'], 'class_observations_schedule_date_unique');
            $table->dropIndex('class_observations_class_schedule_id_observation_date_index');
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX attendances_schedule_student_date_unique');

        Schema::table('class_observations', function (Blueprint $table): void {
            $table->index(['class_schedule_id', 'observation_date'], 'class_observations_class_schedule_id_observation_date_index');
            $table->dropUnique('class_observations_schedule_date_unique');
        });
    }
};
