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
            $table->boolean('reminders_enabled')->default(true);
            $table->json('reminder_days')->nullable();
            $table->boolean('overdue_alerts_enabled')->default(true);
            $table->boolean('review_alerts_enabled')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['reminders_enabled', 'reminder_days', 'overdue_alerts_enabled', 'review_alerts_enabled']);
        });
    }
};
