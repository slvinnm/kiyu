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
        Schema::create('visit_counters', function (Blueprint $table) {
            $table->id();
            $table->date('counter_date')->unique();
            $table->integer('last_sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('queue_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete();
            $table->date('counter_date');
            $table->integer('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['station_id', 'counter_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_counters');
        Schema::dropIfExists('visit_counters');
    }
};
