<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minute_commitment_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_minute_id')->constrained()->cascadeOnDelete();
            $table->string('external_key', 64);
            $table->string('title');
            $table->string('suggested_responsible')->nullable();
            $table->string('suggested_due_date')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamps();
            $table->unique(['meeting_minute_id', 'external_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minute_commitment_proposals');
    }
};
