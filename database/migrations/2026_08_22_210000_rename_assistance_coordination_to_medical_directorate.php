<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('areas')
            ->where('slug', 'coordinacion-asistencial')
            ->update([
                'name' => 'Dirección Médica',
                'slug' => 'direccion-medica',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('areas')
            ->where('slug', 'direccion-medica')
            ->update([
                'name' => 'Coordinación asistencial',
                'slug' => 'coordinacion-asistencial',
                'updated_at' => now(),
            ]);
    }
};
