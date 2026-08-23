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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('approval_policy')->default('both');
        });
        Schema::create('organization_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('approval_type');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'approval_type'], 'organization_approver_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_approvers');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('approval_policy');
        });
    }
};
