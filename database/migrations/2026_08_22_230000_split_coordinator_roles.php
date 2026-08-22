<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereIn('name', [
            'Alejandra Daza', 'Cesar Gallego', 'Geovany Guillermo Rolon',
        ])->where('role', 'coordinator')->update(['role' => 'coordinator_nursing_junior']);

        DB::table('users')->where('name', 'Khaterine Arteaga')->where('role', 'coordinator')
            ->update(['role' => 'coordinator_audit']);

        DB::table('users')->where('role', 'coordinator')->update(['role' => 'coordinator_medical']);
    }

    public function down(): void
    {
        DB::table('users')->whereIn('role', [
            'coordinator_medical', 'coordinator_nursing_junior', 'coordinator_audit',
        ])->update(['role' => 'coordinator']);
    }
};
