<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_verification_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('resident_application_id')
                ->unique()
                ->constrained('resident_applications')
                ->restrictOnDelete();

            $table->foreignId('reviewer_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('resident_id')
                ->nullable()
                ->constrained('residents')
                ->restrictOnDelete();

            $table->string('decision', 20);
            $table->text('note');
            $table->timestamp('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_verification_reviews');
    }
};