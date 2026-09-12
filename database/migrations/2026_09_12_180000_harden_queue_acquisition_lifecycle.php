<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visits') && Schema::hasColumn('visits', 'patient_id')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->unsignedBigInteger('patient_id')->nullable()->change();
            });
        }

        if (! Schema::hasTable('queue_acquisitions')) {
            return;
        }

        Schema::table('queue_acquisitions', function (Blueprint $table) {
            if (! Schema::hasColumn('queue_acquisitions', 'idempotency_key')) {
                $table->uuid('idempotency_key')->nullable()->unique()->after('channel');
            }

            if (! Schema::hasColumn('queue_acquisitions', 'registered_by')) {
                $table->foreignId('registered_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('queue_acquisitions', 'registered_at')) {
                $table->timestamp('registered_at')->nullable()->after('acquired_at');
            }
        });

        Schema::table('queue_acquisitions', function (Blueprint $table) {
            $table->unique('visit_id', 'queue_acquisitions_visit_unique');
            $table->index(['department_id', 'status'], 'queue_acquisitions_department_status_index');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('queue_acquisitions')) {
            Schema::table('queue_acquisitions', function (Blueprint $table) {
                $table->dropUnique('queue_acquisitions_visit_unique');
                $table->dropIndex('queue_acquisitions_department_status_index');

                if (Schema::hasColumn('queue_acquisitions', 'registered_by')) {
                    $table->dropForeign(['registered_by']);
                    $table->dropColumn('registered_by');
                }

                if (Schema::hasColumn('queue_acquisitions', 'registered_at')) {
                    $table->dropColumn('registered_at');
                }

                if (Schema::hasColumn('queue_acquisitions', 'idempotency_key')) {
                    $table->dropUnique(['idempotency_key']);
                    $table->dropColumn('idempotency_key');
                }
            });
        }
    }
};
