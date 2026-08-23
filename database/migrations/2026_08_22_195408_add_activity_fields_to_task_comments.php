<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->string('event_type')->default('comment')->after('body');
            $table->json('metadata')->nullable()->after('event_type');
            $table->index(['task_id', 'created_at']);
        });

        DB::table('tasks')->orderBy('id')->chunkById(200, function ($tasks): void {
            DB::table('task_comments')->insert($tasks->map(fn ($task) => [
                'organization_id' => $task->organization_id,
                'task_id' => $task->id,
                'user_id' => $task->created_by,
                'body' => 'Acción registrada en CUMPLE.',
                'event_type' => 'created',
                'metadata' => json_encode(['initial_status' => $task->status, 'initial_progress' => $task->progress]),
                'is_internal' => false,
                'created_at' => $task->created_at,
                'updated_at' => $task->created_at,
            ])->all());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropIndex(['task_id', 'created_at']);
            $table->dropColumn(['event_type', 'metadata']);
        });
    }
};
