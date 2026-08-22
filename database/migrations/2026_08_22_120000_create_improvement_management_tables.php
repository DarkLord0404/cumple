<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finding_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_invima')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('improvement_cases', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->foreignId('finding_source_id')->constrained()->restrictOnDelete();
            $table->foreignId('reporting_area_id')->constrained('areas')->restrictOnDelete();
            $table->foreignId('reported_area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->date('reported_at');
            $table->string('action_type')->default('improvement');
            $table->text('finding_description');
            $table->string('status')->default('draft');
            $table->unsignedSmallInteger('urgency_score')->nullable();
            $table->unsignedSmallInteger('scope_score')->nullable();
            $table->unsignedSmallInteger('evolution_score')->nullable();
            $table->unsignedSmallInteger('priority_score')->nullable();
            $table->string('analysis_method')->nullable();
            $table->json('analysis_data')->nullable();
            $table->text('immediate_correction')->nullable();
            $table->text('root_cause')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('validated_at')->nullable();
            $table->text('validation_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'reported_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('improvement_case_id')->nullable()->after('meeting_minute_id')->constrained()->cascadeOnDelete();
            $table->string('assignee_type')->default('internal')->after('assigned_to');
            $table->string('external_assignee_name')->nullable()->after('assignee_type');
            $table->string('external_assignee_email')->nullable()->after('external_assignee_name');
            $table->text('required_resources')->nullable()->after('expected_result');
            $table->unsignedSmallInteger('progress')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('improvement_case_id');
            $table->dropColumn(['assignee_type', 'external_assignee_name', 'external_assignee_email', 'required_resources', 'progress']);
        });
        Schema::dropIfExists('improvement_cases');
        Schema::dropIfExists('finding_sources');
    }
};
