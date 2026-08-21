<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->string('topic')->nullable()->after('name');
        });

        Schema::table('homework_pendings', function (Blueprint $table) {
            $table->string('topic')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('topic');
        });

        Schema::table('homework_pendings', function (Blueprint $table) {
            $table->dropColumn('topic');
        });
    }
};
