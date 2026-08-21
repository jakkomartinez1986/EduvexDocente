<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE attendances ALTER COLUMN status TYPE varchar(2)');
            DB::statement('UPDATE attendances SET status = TRIM(status) WHERE status IS NOT NULL');

            return;
        }

        Schema::table('attendances', function (Blueprint $table): void {
            $table->string('status', 2)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE attendances ALTER COLUMN status TYPE char(2)');

            return;
        }

        Schema::table('attendances', function (Blueprint $table): void {
            $table->char('status', 2)->change();
        });
    }
};
