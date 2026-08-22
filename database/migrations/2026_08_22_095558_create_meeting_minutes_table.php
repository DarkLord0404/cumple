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
        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->string('title');
            $table->string('meeting_type')->nullable();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTimeTz('held_at');
            $table->string('location')->nullable();
            $table->text('objective')->nullable();
            $table->text('agenda')->nullable();
            $table->longText('development')->nullable();
            $table->longText('decisions')->nullable();
            $table->string('status')->default('draft');
            $table->dateTimeTz('approved_at')->nullable();
            $table->string('source_document_path')->nullable();
            $table->string('generated_document_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes');
    }
};
