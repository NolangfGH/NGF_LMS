<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('lesson_key', 60);
            $table->unsignedInteger('last_section')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_opened_at');
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('latest_score')->nullable();
            $table->unsignedSmallInteger('best_score')->default(0);
            $table->json('answers')->nullable();
            $table->text('reflection')->nullable();
            $table->string('confidence', 20)->nullable();
            $table->text('help_request')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('completion_evidence')->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->foreignId('feedback_by_teacher_id')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->timestamp('feedback_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'lesson_key']);
        });
        Schema::create('teacher_lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('lesson_key', 60);
            $table->string('status', 20)->default('draft');
            $table->text('instructions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
            $table->unique(['teacher_account_id', 'classroom_id', 'lesson_key'], 'teacher_lesson_section_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_lesson_plans');
        Schema::dropIfExists('lesson_progress');
    }
};
