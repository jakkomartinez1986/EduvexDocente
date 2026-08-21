<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_observations', function (Blueprint $table) {
            $table->string('novedad_type')->nullable()->after('novedad');
        });
    }

    public function down(): void
    {
        Schema::table('class_observations', function (Blueprint $table) {
            $table->dropColumn('novedad_type');
        });
    }
};
