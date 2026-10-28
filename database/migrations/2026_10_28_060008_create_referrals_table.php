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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('target_department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('target_visit_id')->nullable()->constrained('visits')->nullOnDelete();
            $table->string('referred_by_user_id')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, ACCEPTED, COMPLETED, CANCELLED
            $table->tinyInteger('priority')->default(1);
            $table->text('reason')->nullable();
            $table->timestamps();
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
