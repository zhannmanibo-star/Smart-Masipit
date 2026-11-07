<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resident_applications', function (Blueprint $table) {
            $table->string('id_document_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->uuid('identity_evidence_version')->nullable();
            $table->timestamp('identity_submitted_at')->nullable();
            $table->timestamp('identity_reviewed_at')->nullable();

            $table->foreignId('identity_reviewed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('resident_applications', function (Blueprint $table) {
            $table->dropForeign(['identity_reviewed_by']);

            $table->dropColumn([
                'id_document_path',
                'selfie_path',
                'identity_evidence_version',
                'identity_submitted_at',
                'identity_reviewed_at',
                'identity_reviewed_by',
            ]);
        });
    }
};