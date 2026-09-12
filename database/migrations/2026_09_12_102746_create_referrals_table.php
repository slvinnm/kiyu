<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Referrals represent cross-poli transfers where patient enters another service domain/workflow.
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_visit_id')->constrained('visits')->restrictOnDelete();
            $table->foreignId('target_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('target_visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->foreignId('referred_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('PENDING'); // PENDING, ACCEPTED, REJECTED, COMPLETED
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('source_visit_id');
            $table->index('target_department_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
