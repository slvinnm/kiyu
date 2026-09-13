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
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_workflow_step_id')->constrained()->restrictOnDelete();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->string('queue_number'); // A-001, T-018, etc.
            $table->unsignedInteger('priority')->default(1); // 2 = priority, 1 = normal
            $table->unsignedInteger('internal_sequence');
            $table->string('status'); // CREATED, CALLED, IN_PROGRESS, ON_HOLD, COMPLETED, SKIPPED, CANCELLED, NO_SHOW, TRANSFERRED
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transferred_from_ticket_id')->nullable()->constrained('queue_tickets')->nullOnDelete();
            $table->foreignId('transferred_to_station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('visit_id');
            $table->index('station_id');
            $table->index('status');
            $table->index('queue_number');
            $table->index(['station_id', 'status', 'priority', 'internal_sequence']);
            $table->index(['station_id', 'status', 'created_at']);
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
