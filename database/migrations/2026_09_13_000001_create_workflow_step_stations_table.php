<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_step_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['workflow_step_id', 'station_id']);
            $table->index('station_id');
        });

        DB::table('workflow_steps')
            ->select(['id', 'station_id'])
            ->orderBy('id')
            ->eachById(function ($step): void {
                DB::table('workflow_step_stations')->insertOrIgnore([
                    'workflow_step_id' => $step->id,
                    'station_id' => $step->station_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_step_stations');
    }
};
