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
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete();
            $table->string('queue_number');
            $table->tinyInteger('priority')->default(1);
            $table->integer('internal_sequence');
            $table->string('status')->default('CREATED'); // CREATED, CALLED, IN_PROGRESS, ON_HOLD, COMPLETED, SKIPPED, CANCELLED, NO_SHOW, TRANSFERRED
            $table->string('assigned_to')->nullable();
            $table->foreignId('transferred_from_ticket_id')->nullable()->constrained('queue_tickets')->nullOnDelete();
            $table->foreignId('transferred_to_station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['station_id', 'status', 'priority', 'internal_sequence'], 'idx_station_queue_selection');
            $table->index(['visit_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};
