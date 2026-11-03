<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_consultations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('health_patient_id')
                ->constrained('health_patients')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('reference_number', 40)->unique();
            $table->uuid('submission_token');

            // Actual consultation date, separate from encoding time.
            $table->date('consultation_date');

            $table->text('reason_for_visit');

            // Recorded information supplied by authorized health personnel.
            $table->text('consultation_notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['recorded_by', 'submission_token'],
                'health_consultations_submission_unique'
            );

            $table->index(
                ['health_patient_id', 'consultation_date'],
                'health_consultations_patient_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_consultations');
    }
};