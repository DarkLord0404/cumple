<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tasks')
            ->where('code', 'like', 'ACR-%')
            ->where('status', 'in_progress')
            ->where('progress', 50)
            ->update(['progress' => 0, 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // El avance registrado después de esta corrección no debe sobrescribirse.
    }
};
