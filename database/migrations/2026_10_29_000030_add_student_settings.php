<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->json('preferences')->nullable();
            $table->boolean('leaderboard_visible')->default(true);
            $table->unsignedInteger('session_version')->default(1);
            $table->timestamp('password_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['preferences', 'leaderboard_visible', 'session_version', 'password_changed_at']));
    }
};
