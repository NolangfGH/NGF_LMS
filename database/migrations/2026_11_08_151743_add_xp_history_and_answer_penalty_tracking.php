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
        Schema::table('xp_events', function (Blueprint $table): void {
            $table->string('event_key', 160)->nullable();
            $table->string('source_type', 30)->nullable();
            $table->string('source_id', 120)->nullable();
            $table->string('activity_title', 180)->nullable();
            $table->integer('requested_amount')->nullable();
            $table->unsignedInteger('balance_after')->nullable();
            $table->string('request_fingerprint', 64)->nullable();
            $table->json('details')->nullable();
            $table->unique(['student_id', 'event_key'], 'xp_student_event_unique');
        });
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->unsignedTinyInteger('wrong_answer_penalty')->default(0));
        Schema::table('quiz_submissions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('wrong_answer_penalty')->default(0);
            $table->unsignedInteger('xp_deducted')->default(0);
            $table->unsignedInteger('wrong_answers')->default(0);
        });
        Schema::table('course_post_test_attempts', fn (Blueprint $table) => $table->unsignedTinyInteger('wrong_answer_penalty')->default(0));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_post_test_attempts', fn (Blueprint $table) => $table->dropColumn('wrong_answer_penalty'));
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropColumn('wrong_answer_penalty'));
        Schema::table('quiz_submissions', fn (Blueprint $table) => $table->dropColumn(['wrong_answer_penalty', 'xp_deducted', 'wrong_answers']));
        Schema::table('xp_events', function (Blueprint $table): void {
            $table->dropUnique('xp_student_event_unique');
            $table->dropColumn(['event_key', 'source_type', 'source_id', 'activity_title', 'requested_amount', 'balance_after', 'request_fingerprint', 'details']);
        });
    }
};
