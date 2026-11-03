<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->text('pickup_instructions')->nullable();
            $table->timestamp('ready_at')->nullable();

            $table->foreignId('ready_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('released_at')->nullable();

            $table->foreignId('released_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('received_by', 150)->nullable();
            $table->string('recipient_type', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropForeign(['ready_by']);
            $table->dropForeign(['released_by']);

            $table->dropColumn([
                'pickup_instructions',
                'ready_at',
                'ready_by',
                'released_at',
                'released_by',
                'received_by',
                'recipient_type',
            ]);
        });
    }
};