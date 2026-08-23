<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('provider');
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'provider']);
        });

        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->json('external_payload')->nullable()->after('external_reference');
            $table->unique(['organization_id', 'source_system', 'external_reference'], 'meeting_minutes_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_minutes', function (Blueprint $table): void {
            $table->dropUnique('meeting_minutes_external_unique');
            $table->dropColumn('external_payload');
        });
        Schema::dropIfExists('integration_connections');
    }
};
