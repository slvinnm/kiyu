<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_acquisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('status')->default('ACQUIRED');
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acquired_at');
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_acquisitions');
    }
};
