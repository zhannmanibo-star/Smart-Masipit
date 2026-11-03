<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_consultation_addendums', function (Blueprint $table) {
            $table->id();

            $table->foreignId('health_consultation_id')
                ->constrained('health_consultations')
                ->restrictOnDelete();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->uuid('submission_token');

            // Explain why a correction or clarification is necessary.
            $table->text('reason');

            // Preserve the original consultation; record changes separately.
            $table->text('addendum');

            $table->timestamps();

            $table->unique(
                ['recorded_by', 'submission_token'],
                'health_addendums_submission_unique'
            );

            $table->index(
                ['health_consultation_id', 'created_at'],
                'health_addendums_consultation_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_consultation_addendums');
    }
};