<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * H-06 multi-engine: índice único contra carreras de sincronización concurrente.
     *
     * PostgreSQL: Índice único PARCIAL (WHERE deleted_at IS NULL).
     * MySQL/MariaDB: Columna generada almacenada + índice único compuesto.
     *   MySQL ignora NULL en índices únicos, por lo que solo se aplica
     *   a registros activos (deleted_at IS NULL → active_hash = 1).
     * SQLite: Índice único parcial nativo (soporta WHERE en CREATE INDEX).
     *
     * La semántica es idéntica en los tres motores:
     * - Una única asistencia ACTIVA por (class_schedule_id, student_id, date).
     * - Múltiples tombstones / soft deleted permitidos.
     *
     * Solo handlea `attendances`: el índice único de `class_observations` se
     * creó en la migración original y es portable en los tres motores.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Eliminar duplicados activos antes de crear el índice único.
        // (Idempotente: no hace nada cuando no hay duplicados.)
        // Derived table intermedia: compatible con MySQL (1093), PostgreSQL y SQLite.
        DB::statement(
            'DELETE FROM attendances WHERE deleted_at IS NULL AND id NOT IN (SELECT id FROM (SELECT MAX(id) AS id FROM attendances WHERE deleted_at IS NULL GROUP BY class_schedule_id, student_id, date) AS dedupe_attendances)',
        );

        if ($driver === 'pgsql') {
            // PostgreSQL: Índice único parcial — la solución más limpia.
            // La migración original lo crea; aquí se garantiza que exista
            // también cuando la original corrió sin él (rollback parcial).
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS attendances_schedule_student_date_unique ON attendances (class_schedule_id, student_id, date) WHERE deleted_at IS NULL',
            );

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL/MariaDB: Columna generada + índice único compuesto.
            // active_hash = 1 cuando deleted_at IS NULL, NULL en otro caso.
            // MySQL ignora NULL en UNIQUE, logrando la misma semántica.
            if (! Schema::hasColumn('attendances', 'active_hash')) {
                Schema::table('attendances', function (Blueprint $table): void {
                    $table->tinyInteger('active_hash')->storedAs('IF(deleted_at IS NULL, 1, NULL)')->nullable();
                });
            }

            Schema::table('attendances', function (Blueprint $table): void {
                $table->unique(
                    ['class_schedule_id', 'student_id', 'date', 'active_hash'],
                    'attendances_schedule_student_date_unique',
                );
            });
        }
        // SQLite ya tiene el índice único parcial de la migración original.
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS attendances_schedule_student_date_unique');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->dropIndex('attendances_schedule_student_date_unique');
            });

            if (Schema::hasColumn('attendances', 'active_hash')) {
                Schema::table('attendances', function (Blueprint $table): void {
                    $table->dropColumn('active_hash');
                });
            }
        }
    }
};
