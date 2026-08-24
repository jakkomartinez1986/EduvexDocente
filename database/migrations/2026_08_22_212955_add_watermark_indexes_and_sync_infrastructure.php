<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fase 8: infraestructura de sincronización.
     *
     * - `sync_tombstones`: registro de entidades eliminadas para el pull
     *   incremental (el cliente borra la fila local correspondiente).
     * - Índices watermark: consultas incrementales `updated_at > cursor`
     *   y búsquedas de tombstones por `deleted_at`.
     */
    public function up(): void
    {
        Schema::create('sync_tombstones', function (Blueprint $table): void {
            $table->id();
            $table->string('entity', 64);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->timestamp('deleted_at');
            $table->index(['entity', 'owner_user_id', 'deleted_at'], 'sync_tombstones_scope_index');
            $table->unique(['entity', 'entity_id'], 'sync_tombstones_entity_unique');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->index('updated_at', 'attendances_updated_at_index');
            $table->index('deleted_at', 'attendances_deleted_at_index');
        });

        Schema::table('activity_grades', function (Blueprint $table): void {
            $table->index('updated_at', 'activity_grades_updated_at_index');
            $table->index('deleted_at', 'activity_grades_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_tombstones');

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropIndex('attendances_updated_at_index');
            $table->dropIndex('attendances_deleted_at_index');
        });

        Schema::table('activity_grades', function (Blueprint $table): void {
            $table->dropIndex('activity_grades_updated_at_index');
            $table->dropIndex('activity_grades_deleted_at_index');
        });
    }
};
