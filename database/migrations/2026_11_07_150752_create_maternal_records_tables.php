<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maternal_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('health_patient_id')
                ->constrained('health_patients')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('reference_number', 40)->unique();
            $table->uuid('submission_token');

            $table->date('record_date');
            $table->json('patient_snapshot');
            $table->json('details');

            $table->timestamps();

            $table->unique(
                ['recorded_by', 'submission_token'],
                'maternal_records_submission_unique'
            );

            $table->index(
                ['health_patient_id', 'record_date'],
                'maternal_records_patient_date_index'
            );
        });

        Schema::create('maternal_visits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('maternal_record_id')
                ->constrained('maternal_records')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->uuid('submission_token');
            $table->date('visit_date');
            $table->json('details');
            $table->timestamps();

            $table->unique(
                ['recorded_by', 'submission_token'],
                'maternal_visits_submission_unique'
            );

            $table->index(
                ['maternal_record_id', 'visit_date'],
                'maternal_visits_record_date_index'
            );
        });

        Schema::create('maternal_addendums', function (Blueprint $table) {
            $table->id();

            $table->foreignId('maternal_record_id')
                ->constrained('maternal_records')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->uuid('submission_token');
            $table->text('reason');
            $table->text('addendum');
            $table->timestamps();

            $table->unique(
                ['recorded_by', 'submission_token'],
                'maternal_addendums_submission_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternal_addendums');
        Schema::dropIfExists('maternal_visits');
        Schema::dropIfExists('maternal_records');
    }
};