<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_history_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('health_patient_id')
                ->constrained('health_patients')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('category', 40);
            $table->unsignedInteger('version');

            $table->string('finding_status', 30);
            $table->text('details')->nullable();

            $table->string('information_source', 30);
            $table->text('source_details');

            // Date of the interview or source document.
            $table->date('source_date');

            $table->text('change_reason');
            $table->uuid('submission_token');
            $table->timestamps();

            $table->unique(
                ['health_patient_id', 'category', 'version'],
                'health_history_version_unique'
            );

            $table->unique(
                ['recorded_by', 'submission_token'],
                'health_history_submission_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_history_entries');
    }
};