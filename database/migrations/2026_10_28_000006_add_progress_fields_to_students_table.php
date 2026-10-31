<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('streak_days')->default(0);
            $table->unsignedInteger('best_streak')->default(0);
            $table->unsignedInteger('level')->default(1);
            $table->unsignedInteger('badges_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['xp', 'streak_days', 'best_streak', 'level', 'badges_count']);
        });
    }
};
