<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('improvement_cases', function (Blueprint $table) {
            $table->text('impact_before')->nullable()->after('root_cause');
            $table->text('impact_after')->nullable()->after('impact_before');
            $table->text('effectiveness_result')->nullable()->after('impact_after');
            $table->boolean('is_effective')->nullable()->after('effectiveness_result');
            $table->foreignId('effectiveness_evaluated_by')->nullable()->after('is_effective')->constrained('users')->nullOnDelete();
            $table->timestampTz('effectiveness_evaluated_at')->nullable()->after('effectiveness_evaluated_by');
            $table->text('closure_notes')->nullable()->after('effectiveness_evaluated_at');
            $table->timestampTz('closed_at')->nullable()->after('closure_notes');
        });
    }

    public function down(): void
    {
        Schema::table('improvement_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('effectiveness_evaluated_by');
            $table->dropColumn(['impact_before', 'impact_after', 'effectiveness_result', 'is_effective', 'effectiveness_evaluated_at', 'closure_notes', 'closed_at']);
        });
    }
};
