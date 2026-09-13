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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('workflow_version_id')->constrained()->restrictOnDelete();
            $table->string('visit_number')->unique();
            $table->unsignedInteger('priority')->default(1); // 1=NORMAL, 2=PRIORITY, 3=EMERGENCY
            $table->string('intake_channel'); // ONLINE, KIOSK, WALK_IN
            $table->string('status'); // AWAITING_CHECKIN, CHECKED_IN, IN_PROGRESS, COMPLETED, CANCELLED
            $table->string('online_active_key')->nullable()->unique();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('patient_id');
            $table->index('department_id');
            $table->index('status');
            $table->index(['patient_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
