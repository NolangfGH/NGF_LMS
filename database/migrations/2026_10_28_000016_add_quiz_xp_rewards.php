<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_submissions', function (Blueprint $table) {
            $table->unsignedInteger('answer_xp')->default(0);
            $table->unsignedInteger('perfect_bonus_xp')->default(0);
            $table->timestamp('xp_awarded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_submissions', fn (Blueprint $table) => $table->dropColumn(['answer_xp', 'perfect_bonus_xp', 'xp_awarded_at']));
    }
};
