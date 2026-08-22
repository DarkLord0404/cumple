<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('quality_approved_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->dateTimeTz('quality_approved_at')->nullable()->after('quality_approved_by');
            $table->foreignId('medical_approved_by')->nullable()->after('quality_approved_at')->constrained('users')->nullOnDelete();
            $table->dateTimeTz('medical_approved_at')->nullable()->after('medical_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quality_approved_by');
            $table->dropConstrainedForeignId('medical_approved_by');
            $table->dropColumn(['quality_approved_at', 'medical_approved_at']);
        });
    }
};
