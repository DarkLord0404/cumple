<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('kairo_minute_visibility')->default('administrators')->after('minute_template_name');
        });

        Schema::create('organization_kairo_minute_viewers', function (Blueprint $table): void {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_kairo_minute_viewers');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('kairo_minute_visibility'));
    }
};
