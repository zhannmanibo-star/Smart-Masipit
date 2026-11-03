<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_immunizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('health_patient_id')
                ->constrained('health_patients')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('reference_number', 40)->unique();
            $table->uuid('submission_token');

            $table->string('vaccine_name', 150);
            $table->string('dose_label', 100)->nullable();
            $table->date('administered_on');

            $table->string('administered_by', 150)->nullable();
            $table->string('facility', 200)->nullable();
            $table->string('lot_number', 100)->nullable();

            $table->text('source_details');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['recorded_by', 'submission_token'],
                'health_immunization_submission_unique'
            );

            $table->index(
                ['health_patient_id', 'administered_on'],
                'health_immunization_patient_date_index'
            );
        });

        Schema::create(
            'health_immunization_corrections',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('health_immunization_id')
                    ->constrained('health_immunizations')
                    ->restrictOnDelete();

                $table->foreignId('recorded_by')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->uuid('submission_token');

                $table->text('reason');
                $table->text('correction');
                $table->text('source_details');

                $table->timestamps();

                $table->unique(
                    ['recorded_by', 'submission_token'],
                    'health_immunization_correction_token_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('health_immunization_corrections');
        Schema::dropIfExists('health_immunizations');
    }
};