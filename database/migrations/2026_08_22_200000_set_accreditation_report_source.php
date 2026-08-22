<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sourceId = DB::table('finding_sources')->where('name', 'Informe de acreditación – acreditación condicionada')->value('id');
        if (! $sourceId) {
            $sourceId = DB::table('finding_sources')->insertGetId([
                'name' => 'Informe de acreditación – acreditación condicionada',
                'is_invima' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('improvement_cases')->where('code', 'like', 'ACR-%')->update([
            'finding_source_id' => $sourceId,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $originalSourceId = DB::table('finding_sources')->where('name', 'Autoevaluación de acreditación RES 5095/2018')->value('id');
        if ($originalSourceId) {
            DB::table('improvement_cases')->where('code', 'like', 'ACR-%')->update([
                'finding_source_id' => $originalSourceId,
                'updated_at' => now(),
            ]);
        }
    }
};
