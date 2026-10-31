<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coding_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions');
            $table->text('sample_output');
            $table->unsignedSmallInteger('time_limit_minutes');
            $table->unsignedInteger('winner_xp')->default(100);
            $table->string('award_title', 80)->default('Coding Challenge Winner');
            $table->string('status', 20)->default('inviting');
            $table->timestamp('started_at', 6)->nullable();
            $table->timestamp('deadline_at', 6)->nullable();
            $table->unsignedBigInteger('winning_submission_id')->nullable();
            $table->timestamp('awarded_at', 6)->nullable();
            $table->text('award_note')->nullable();
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamp('cancelled_at', 6)->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps(6);
            $table->index(['teacher_account_id', 'status']);
        });
        Schema::create('coding_match_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coding_match_id')->constrained('coding_matches')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->string('student_name');
            $table->string('section_label', 350);
            $table->string('response', 20)->default('invited');
            $table->timestamp('accepted_at', 6)->nullable();
            $table->mediumText('draft_code');
            $table->unsignedInteger('draft_revision')->default(0);
            $table->timestamp('draft_saved_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['coding_match_id', 'slot'], 'coding_match_slot_unique');
            $table->unique(['coding_match_id', 'student_id'], 'coding_match_student_unique');
            $table->index(['student_id', 'response']);
        });
        Schema::create('coding_match_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('coding_match_participants')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->mediumText('code');
            $table->timestamp('submitted_at', 6);
            $table->boolean('on_time');
            $table->string('verdict', 20)->default('pending');
            $table->text('observed_output')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('reviewed_at', 6)->nullable();
            $table->foreignId('reviewed_by_teacher_id')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->unique(['participant_id', 'revision'], 'coding_submission_revision_unique');
        });
        Schema::table('coding_matches', fn (Blueprint $table) => $table->foreign('winning_submission_id')->references('id')->on('coding_match_submissions')->nullOnDelete());
        Schema::create('challenge_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coding_match_id')->constrained('coding_matches')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event_key', 120);
            $table->string('message', 500);
            $table->timestamp('created_at', 6);
            $table->timestamp('read_at', 6)->nullable();
            $table->unique(['coding_match_id', 'student_id', 'event_key'], 'coding_notice_student_unique');
            $table->unique(['coding_match_id', 'teacher_account_id', 'event_key'], 'coding_notice_teacher_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_notices');
        Schema::table('coding_matches', fn (Blueprint $table) => $table->dropForeign(['winning_submission_id']));
        Schema::dropIfExists('coding_match_submissions');
        Schema::dropIfExists('coding_match_participants');
        Schema::dropIfExists('coding_matches');
    }
};
