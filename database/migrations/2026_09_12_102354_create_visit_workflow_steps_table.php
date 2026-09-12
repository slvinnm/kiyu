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
        Schema::create('visit_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_workflow_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->restrictOnDelete();
            $table->string('status'); // PENDING, IN_PROGRESS, COMPLETED, SKIPPED, CANCELLED
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['visit_workflow_id', 'workflow_step_id']);
            $table->index('workflow_step_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_workflow_steps');
    }
};
