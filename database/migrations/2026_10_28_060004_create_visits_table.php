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
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->string('visit_number')->unique();
            $table->tinyInteger('priority')->default(1); // 1 = NORMAL, 2 = PRIORITY, 3 = EMERGENCY
            $table->string('intake_channel'); // ONLINE, KIOSK, MANUAL
            $table->string('status')->default('WAITING'); // AWAITING_CHECKIN, WAITING, IN_PROGRESS, COMPLETED, CANCELLED
            $table->string('online_active_key')->nullable()->index();
            $table->string('registered_by')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
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
