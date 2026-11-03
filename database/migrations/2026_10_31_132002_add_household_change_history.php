<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->unsignedInteger('location_version')->default(0);
        });

        Schema::create('household_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('household_id')
                ->constrained('households')
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('action', 50);
            $table->json('before_values');
            $table->json('after_values');
            $table->text('reason');
            $table->timestamp('occurred_at');

            $table->index(['household_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_changes');

        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('location_version');
        });
    }
};