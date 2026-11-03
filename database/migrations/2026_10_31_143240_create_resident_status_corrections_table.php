<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_status_corrections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('resident_id')
                ->constrained('residents')
                ->restrictOnDelete();

            // Only one pending request per resident.
            // Cleared after approval or rejection.
            $table->unsignedBigInteger('pending_resident_id')
                ->nullable()
                ->unique();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->unsignedInteger('resident_status_version');
            $table->date('original_date_of_death');
            $table->date('proposed_date_of_death')->nullable();

            $table->text('reason');
            $table->string('supporting_reference', 255);
            $table->string('status', 20)->default('pending');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['resident_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_status_corrections');
    }
};