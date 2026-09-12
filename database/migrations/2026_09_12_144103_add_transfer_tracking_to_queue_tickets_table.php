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
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->foreignId('transferred_from_ticket_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('queue_tickets')
                ->nullOnDelete();
            $table->foreignId('transferred_to_station_id')
                ->nullable()
                ->after('transferred_from_ticket_id')
                ->constrained('stations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transferred_from_ticket_id');
            $table->dropConstrainedForeignId('transferred_to_station_id');
        });
    }
};
