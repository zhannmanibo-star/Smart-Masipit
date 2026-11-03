<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 40)->unique();

            $table->foreignId('resident_id')
                ->constrained('residents')
                ->restrictOnDelete();

            $table->foreignId('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->uuid('submission_token');
            $table->string('document_type', 50);
            $table->text('purpose');
            $table->string('status', 30)->default('submitted');
            $table->string('source', 20)->default('online');
            $table->json('resident_snapshot');
            $table->timestamps();

            $table->unique(
                ['requested_by', 'submission_token'],
                'document_requests_submission_unique'
            );

            $table->index(['resident_id', 'status']);
        });

        Schema::create('document_request_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('document_request_id')
                ->constrained('document_requests')
                ->restrictOnDelete();

            $table->foreignId('actor_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_request_events');
        Schema::dropIfExists('document_requests');
    }
};