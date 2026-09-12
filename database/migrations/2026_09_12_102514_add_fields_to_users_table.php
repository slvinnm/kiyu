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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('password'); // admin, receptionist, nurse, doctor, pharmacy, lab
            $table->foreignId('department_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('station_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('role');
        });
    }
};
