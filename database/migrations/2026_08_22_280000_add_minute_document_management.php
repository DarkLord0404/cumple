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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('minute_template_path')->nullable();
            $table->string('minute_template_name')->nullable();
        });
        Schema::create('minute_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_minute_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['meeting_minute_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minute_document_versions');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['minute_template_path', 'minute_template_name']);
        });
    }
};
