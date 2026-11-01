<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_bee_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('category', 20);
            $table->string('status', 20)->default('draft')->index();
            $table->json('questions');
            $table->unsignedBigInteger('current_run_id')->nullable();
            $table->timestamp('started_at', 6)->nullable();
            $table->timestamp('completed_at', 6)->nullable();
            $table->timestamps();
        });
        Schema::create('quiz_bee_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_bee_match_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->foreignId('captain_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestamp('ready_at', 6)->nullable();
        });
        Schema::create('quiz_bee_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_bee_match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_bee_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->unique(['quiz_bee_match_id', 'student_id']);
        });
        Schema::create('quiz_bee_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_bee_match_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('level', 20);
            $table->string('kind', 20);
            $table->unsignedTinyInteger('question_index');
            $table->json('question');
            $table->json('eligible_team_ids');
            $table->timestamp('opened_at', 6);
            $table->timestamp('closes_at', 6);
            $table->timestamp('closed_at', 6)->nullable();
            $table->unique(['quiz_bee_match_id', 'sequence']);
        });
        Schema::create('quiz_bee_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_bee_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_bee_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('answer')->nullable();
            $table->boolean('correct');
            $table->unsignedInteger('elapsed_ms');
            $table->unsignedTinyInteger('xp')->default(0);
            $table->timestamp('submitted_at', 6)->nullable();
            $table->unique(['quiz_bee_run_id', 'quiz_bee_team_id']);
        });
    }

    public function down(): void
    {
        foreach (['quiz_bee_answers', 'quiz_bee_runs', 'quiz_bee_members', 'quiz_bee_teams', 'quiz_bee_matches'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
