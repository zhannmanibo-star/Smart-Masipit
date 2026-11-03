<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->unsignedInteger('status_version')->default(0);
        });

        Schema::create('resident_status_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('resident_id')
                ->constrained('residents')
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('previous_date_of_death')->nullable();
            $table->date('new_date_of_death')->nullable();
            $table->text('reason');
            $table->timestamp('occurred_at');

            $table->index(['resident_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_status_events');

        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn('status_version');
        });
    }
};