<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();

            $table->string('household_number', 40)->unique();

            $table->foreignId('head_resident_id')
                ->unique()
                ->constrained('residents')
                ->restrictOnDelete();

            $table->string('sitio', 100)->index();
            $table->string('address', 255)->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });

        Schema::create('household_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('household_id')
                ->constrained('households')
                ->restrictOnDelete();

            // A resident can have only one current membership.
            $table->foreignId('resident_id')
                ->unique()
                ->constrained('residents')
                ->restrictOnDelete();

            $table->string('relationship_to_head', 50);

            $table->foreignId('added_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_members');
        Schema::dropIfExists('households');
    }
};