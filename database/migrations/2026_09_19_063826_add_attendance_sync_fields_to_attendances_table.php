<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos offline-first del módulo de asistencia (API v1 Flutter/Android):
     *
     * - teacher_id: docente que registró la fila (siempre = class_schedules.teacher_id).
     * - client_uuid: uuid por registro generado por el cliente, con índice único
     *   SOLO sobre filas activas (multi-engine, mismo patrón que
     *   attendances_schedule_student_date_unique) para que el replay de un
     *   snapshot ya consumido sea idempotente y ningún registro offline se
     *   duplique al re-subir con la misma uuid.
     * - novedad / novedad_type: novedad ortogonal al estado (catalogada en
     *   ClassObservation::NOVEDAD_TYPES).
     * - recorded_at: instante en que se capturó el registro en el servidor.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        Schema::table('attendances', function (Blueprint $table): void {
            $table->unsignedBigInteger('teacher_id')->nullable()->after('tutor_id');
            $table->uuid('client_uuid')->nullable()->after('date');
            $table->text('novedad')->nullable()->after('justification_file_path');
            $table->string('novedad_type')->nullable()->after('novedad');
            $table->timestamp('recorded_at')->nullable()->after('updated_at');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->foreign('teacher_id')
                ->references('id')
                ->on('teachers')
                ->nullOnDelete();
        });

        if ($driver === 'sqlite') {
            // SQLite no soporta ADD CONSTRAINT: Laravel reconstruye la tabla al
            // añadir la FK y la reconstrucción recrea los índices únicos
            // parciales SIN el WHERE, dejándolos prohibitivos a los tombstones.
            // Se restaura la semántica del índice de tupla de la migración
            // add_unique_keys_to_attendance_tables: solo una fila ACTIVA por
            // (class_schedule_id, student_id, date), múltiples tombstones OK.
            DB::statement('DROP INDEX IF EXISTS attendances_schedule_student_date_unique');
            DB::statement('CREATE UNIQUE INDEX attendances_schedule_student_date_unique ON attendances (class_schedule_id, student_id, date) WHERE deleted_at IS NULL');
        }

        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX attendances_client_uuid_unique ON attendances (client_uuid) WHERE deleted_at IS NULL');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL ignora NULL en índices únicos: reusar active_hash (1 para
            // filas activas, NULL para tombstones) logra la misma semántica.
            if (Schema::hasColumn('attendances', 'active_hash')) {
                Schema::table('attendances', function (Blueprint $table): void {
                    $table->unique(['client_uuid', 'active_hash'], 'attendances_client_uuid_unique');
                });
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql' || $driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS attendances_client_uuid_unique');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::table('attendances', function (Blueprint $table): void {
                $table->dropIndex('attendances_client_uuid_unique');
            });
        }

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropForeign(['teacher_id']);
            $table->dropColumn(['teacher_id', 'client_uuid', 'novedad', 'novedad_type', 'recorded_at']);
        });

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS attendances_schedule_student_date_unique');
            DB::statement('CREATE UNIQUE INDEX attendances_schedule_student_date_unique ON attendances (class_schedule_id, student_id, date) WHERE deleted_at IS NULL');
        }
    }
};
