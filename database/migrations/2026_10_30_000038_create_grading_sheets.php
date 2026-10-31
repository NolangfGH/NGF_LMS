<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('school_year', 9);
            $table->string('term', 5);
            $table->json('weights');
            $table->decimal('passing_score', 5, 2)->default(75);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['classroom_id', 'school_year', 'term']);
        });
        Schema::create('grading_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_sheet_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('category', 2);
            $table->decimal('max_score', 9, 2);
            $table->date('due_date')->nullable();
            $table->string('competency', 200)->nullable();
            $table->text('instructions')->nullable();
            $table->string('source_type', 10)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();
            $table->unique(['grading_sheet_id', 'source_type', 'source_id'], 'grading_source_unique');
        });
        Schema::create('grading_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 9, 2)->nullable();
            $table->string('status', 10);
            $table->foreignId('scored_by')->constrained('teacher_accounts');
            $table->timestamps();
            $table->unique(['grading_assessment_id', 'student_id']);
        });
        Schema::create('grading_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->timestamps();
            $table->unique(['grading_sheet_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_notes');
        Schema::dropIfExists('grading_scores');
        Schema::dropIfExists('grading_assessments');
        Schema::dropIfExists('grading_sheets');
    }
};
