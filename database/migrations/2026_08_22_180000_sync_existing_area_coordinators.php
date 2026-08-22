<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'coordinator')->where('is_active', true)
            ->whereNotNull('area_id')->orderBy('id')->get(['id', 'area_id'])
            ->each(fn ($user) => DB::table('areas')->where('id', $user->area_id)
                ->whereNull('coordinator_id')->update(['coordinator_id' => $user->id]));
    }

    public function down(): void
    {
        // Las asignaciones pueden haberse editado después; no se eliminan al revertir.
    }
};
