<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('improvement_cases', function (Blueprint $table) {
            $table->string('institutional_consecutive')->nullable()->after('code');
            $table->string('reported_person_name')->nullable()->after('reported_by');
            $table->string('reported_person_position')->nullable()->after('reported_person_name');
        });
    }

    public function down(): void
    {
        Schema::table('improvement_cases', fn (Blueprint $table) => $table->dropColumn(['institutional_consecutive', 'reported_person_name', 'reported_person_position']));
    }
};
