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
        Schema::create('queue_acquisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('channel'); // ONLINE, KIOSK, MANUAL
            $table->string('status')->default('ACQUIRED'); // ACQUIRED, REGISTERED, CANCELLED
            $table->string('idempotency_key')->nullable()->index();
            $table->string('registered_by')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_acquisitions');
    }
};
