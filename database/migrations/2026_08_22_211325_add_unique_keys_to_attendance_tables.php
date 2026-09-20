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
     *
     * NOTA: Para soporte MySQL/MariaDB, ver la migración
     * alter_attendance_unique_index_for_multi_engine.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Derived table intermedia: MySQL (1093) exige no apuntar a la misma
        // tabla en la subconsulta de un DELETE; la materialización lo permite
        // y es válido también en PostgreSQL y SQLite.
        DB::statement('DELETE FROM attendances WHERE deleted_at IS NULL AND id NOT IN (SELECT id FROM (SELECT MAX(id) AS id FROM attendances WHERE deleted_at IS NULL GROUP BY class_schedule_id, student_id, date) AS dedupe_attendances)');

        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX attendances_schedule_student_date_unique ON attendances (class_schedule_id, student_id, date) WHERE deleted_at IS NULL');
        }

        DB::statement('DELETE FROM class_observations WHERE id NOT IN (SELECT id FROM (SELECT MAX(id) AS id FROM class_observations GROUP BY class_schedule_id, observation_date) AS dedupe_class_observations)');

        Schema::table('class_observations', function (Blueprint $table): void {
            $table->unique(['class_schedule_id', 'observation_date'], 'class_observations_schedule_date_unique');
            $table->dropIndex('class_observations_class_schedule_id_observation_date_index');
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement('DROP INDEX attendances_schedule_student_date_unique');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->dropIndex('attendances_schedule_student_date_unique');
            });
        }

        Schema::table('class_observations', function (Blueprint $table): void {
            $table->index(['class_schedule_id', 'observation_date'], 'class_observations_class_schedule_id_observation_date_index');
            $table->dropUnique('class_observations_schedule_date_unique');
        });
    }
};
