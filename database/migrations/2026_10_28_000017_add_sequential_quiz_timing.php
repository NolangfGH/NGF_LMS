<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->boolean('sequential')->default(false);
            $table->unsignedInteger('current_question')->default(0);
            $table->dateTime('question_started_at', 6)->nullable();
            $table->json('question_timings')->nullable();
        });
        Schema::table('quiz_submissions', fn (Blueprint $table) => $table->unsignedInteger('speed_bonus_xp')->default(0));
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropColumn(['sequential','current_question','question_started_at','question_timings']));
        Schema::table('quiz_submissions', fn (Blueprint $table) => $table->dropColumn('speed_bonus_xp'));
    }
};
