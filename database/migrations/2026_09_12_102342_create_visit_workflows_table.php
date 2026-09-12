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
        Schema::create('visit_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_version_id')->constrained()->restrictOnDelete();
            $table->string('status'); // ACTIVE, COMPLETED, CANCELLED
            $table->timestamps();

            $table->unique('visit_id');
            $table->index('workflow_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visit_workflows');
    }
};
