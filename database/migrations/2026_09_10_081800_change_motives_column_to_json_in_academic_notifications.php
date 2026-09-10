<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cambia motives de jsonb a json para portabilidad multi-motor.
     *
     * En PostgreSQL, jsonb y json son tipos distintos: jsonb está indexado
     * internamente y no preserva el orden de las claves; json es texto plano
     * validado. En MySQL/MariaDB solo existe JSON.
     *
     * Laravel mapea $table->json() a jsonb en PostgreSQL y json en MySQL/
     * MariaDB de forma transparente. Las operaciones de Eloquent (insert,
     * update, read, null, array) funcionan de manera equivalente en ambos.
     */
    public function up(): void
    {
        Schema::table('academic_notifications', function (Blueprint $table) {
            $table->json('motives')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * jsonb solo existe en PostgreSQL; en MySQL/MariaDB/SQLite el tipo se creó
     * como json y no hay un tipo "jsonb" al que revertir, así que el rollback
     * es un no-op para esos motores (el schema queda igual).
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('academic_notifications', function (Blueprint $table) {
            $table->jsonb('motives')->nullable()->change();
        });
    }
};
