<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_patients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('resident_id')
                ->unique()
                ->constrained('residents')
                ->restrictOnDelete();

            $table->foreignId('registered_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_patients');
    }
};