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
        Schema::create('queue_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_ticket_id')->constrained('queue_tickets')->cascadeOnDelete();
            $table->string('event_type'); // CREATED, CALLED, STARTED, COMPLETED, HELD, RESUMED, SKIPPED, CANCELLED, NO_SHOW, TRANSFERRED
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('user_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['queue_ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_events');
    }
};
