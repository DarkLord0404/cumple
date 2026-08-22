<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['users', 'areas', 'finding_sources', 'improvement_cases', 'meeting_minutes', 'tasks', 'evidences', 'task_comments', 'official_documents'];

    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        $organizationId = DB::table('organizations')->insertGetId([
            'name' => 'Clínica de Occidente',
            'slug' => 'clinica-de-occidente',
            'contact_email' => 'info@koqoi.com',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ($this->tables as $tableName) {
            DB::table($tableName)->whereNull('organization_id')->update(['organization_id' => $organizationId]);
        }

        Schema::table('areas', function (Blueprint $table): void {
            $table->dropUnique('areas_slug_unique');
            $table->unique(['organization_id', 'slug']);
        });
        Schema::table('finding_sources', function (Blueprint $table): void {
            $table->dropUnique('finding_sources_name_unique');
            $table->unique(['organization_id', 'name']);
        });
        Schema::table('improvement_cases', function (Blueprint $table): void {
            $table->dropUnique('improvement_cases_code_unique');
            $table->unique(['organization_id', 'code']);
        });
        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->dropUnique('meeting_minutes_number_unique');
            $table->unique(['organization_id', 'number']);
        });
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropUnique('tasks_code_unique');
            $table->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        }
        Schema::dropIfExists('organizations');
    }
};
