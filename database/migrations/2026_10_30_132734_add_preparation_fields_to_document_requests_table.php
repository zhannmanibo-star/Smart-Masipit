<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->string('control_number', 50)->nullable()->unique();
            $table->date('issuance_date')->nullable();
            $table->string('signatory_name', 150)->nullable();
            $table->timestamp('prepared_at')->nullable();

            $table->foreignId('prepared_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropForeign(['prepared_by']);
            $table->dropUnique(['control_number']);

            $table->dropColumn([
                'control_number',
                'issuance_date',
                'signatory_name',
                'prepared_at',
                'prepared_by',
            ]);
        });
    }
};