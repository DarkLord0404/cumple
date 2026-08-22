<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_user', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['task_id', 'user_id']);
            $table->index(['user_id', 'task_id']);
        });

        DB::table('tasks')->whereNotNull('assigned_to')->orderBy('id')->eachById(function ($task): void {
            DB::table('task_user')->insertOrIgnore([
                'task_id' => $task->id, 'user_id' => $task->assigned_to,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user');
    }
};
