<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_consultations', function (Blueprint $table) {
            $table->unsignedSmallInteger('bp_systolic')->nullable();
            $table->unsignedSmallInteger('bp_diastolic')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('health_consultations', function (Blueprint $table) {
            $table->dropColumn([
                'bp_systolic',
                'bp_diastolic',
            ]);
        });
    }
};