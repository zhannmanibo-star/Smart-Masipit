<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_access_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('health_patient_id')
                ->nullable()
                ->constrained('health_patients')
                ->restrictOnDelete();

            $table->string('action', 50);
            $table->timestamp('occurred_at');

            $table->index(['health_patient_id', 'occurred_at']);
            $table->index(['actor_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_access_logs');
    }
};