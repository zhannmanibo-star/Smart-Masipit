<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status')
                ->default('pending')
                ->index();

            $table->foreignId('resident_id')
                ->nullable()
                ->unique()
                ->constrained('residents')
                ->restrictOnDelete();

            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('verified_at')->nullable();

            $table->text('rejection_reason')->nullable();
        });

        // Preserve access for existing system administrators.
        // This does not verify their barangay residency.
        DB::table('users')
            ->where('role', 'admin')
            ->update(['account_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['resident_id']);
            $table->dropForeign(['verified_by']);
            $table->dropUnique(['resident_id']);
            $table->dropIndex(['account_status']);

            $table->dropColumn([
                'account_status',
                'resident_id',
                'verified_by',
                'verified_at',
                'rejection_reason',
            ]);
        });
    }
};