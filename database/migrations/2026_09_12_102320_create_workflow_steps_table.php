<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('station_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('sequence');
            $table->boolean('requires_queue')->default(true);
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_repeatable')->default(false);
            $table->boolean('can_skip')->default(false);
            $table->json('completion_requirements')->nullable();
            $table->json('entry_conditions')->nullable();
            $table->timestamps();

            $table->unique(['workflow_version_id', 'sequence']);
            $table->index('station_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
