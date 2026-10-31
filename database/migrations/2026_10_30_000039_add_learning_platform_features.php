<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->json('test_cases')->nullable();
            $table->json('hints')->nullable();
        });
        Schema::create('code_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('code_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('request_key', 64);
            $table->string('mode', 8);
            $table->string('status', 20)->default('preparing');
            $table->text('code');
            $table->json('cases');
            $table->json('tokens')->nullable();
            $table->json('results')->nullable();
            $table->unsignedInteger('earned')->default(0);
            $table->unsignedInteger('possible')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'request_key']);
            $table->index(['teacher_account_id', 'status', 'created_at']);
        });
        Schema::table('lab_submissions', fn (Blueprint $table) => $table->foreignId('code_run_id')->nullable()->constrained()->nullOnDelete());
        Schema::create('xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason', 120);
            $table->boolean('counts_for_period')->default(true);
            $table->timestamp('created_at');
            $table->index(['student_id', 'created_at']);
        });
        DB::table('students')->where('xp', '>', 0)->orderBy('id')->chunkById(200, function ($students) {
            DB::table('xp_events')->insert($students->map(fn ($student) => ['student_id' => $student->id, 'amount' => $student->xp, 'reason' => 'Existing XP balance', 'counts_for_period' => false, 'created_at' => now()])->all());
        });
        Schema::create('learning_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 120);
            $table->unsignedInteger('amount');
            $table->timestamp('created_at');
            $table->unique(['student_id', 'event_key']);
        });
        Schema::table('classrooms', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable();
            $table->boolean('sequential_learning')->default(false);
            $table->unsignedInteger('weekly_xp_goal')->default(1000);
        });
        Schema::table('teacher_accounts', fn (Blueprint $table) => $table->string('role', 20)->default('teacher'));
        Schema::create('lesson_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->string('lesson_key', 60);
            $table->json('definition');
            $table->string('status', 10)->default('draft');
            $table->string('image_path')->nullable();
            $table->string('image_mime', 30)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['teacher_account_id', 'lesson_key']);
        });
        Schema::table('teacher_quizzes', function (Blueprint $table) {
            $table->boolean('untimed')->default(false);
            $table->unsignedSmallInteger('question_seconds')->default(60);
        });
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->boolean('untimed')->default(false);
            $table->unsignedSmallInteger('question_seconds')->default(60);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropColumn(['untimed', 'question_seconds']));
        Schema::table('teacher_quizzes', fn (Blueprint $table) => $table->dropColumn(['untimed', 'question_seconds']));
        Schema::dropIfExists('lesson_contents');
        Schema::table('teacher_accounts', fn (Blueprint $table) => $table->dropColumn('role'));
        Schema::table('classrooms', fn (Blueprint $table) => $table->dropColumn(['archived_at', 'sequential_learning', 'weekly_xp_goal']));
        Schema::dropIfExists('learning_rewards');
        Schema::dropIfExists('xp_events');
        Schema::table('lab_submissions', fn (Blueprint $table) => $table->dropConstrainedForeignId('code_run_id'));
        Schema::dropIfExists('code_runs');
        Schema::table('teacher_assignments', fn (Blueprint $table) => $table->dropColumn(['test_cases', 'hints']));
    }
};
