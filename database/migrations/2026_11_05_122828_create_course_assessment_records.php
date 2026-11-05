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
        Schema::create('course_assessment_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('assessment', 24);
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('total_questions');
            $table->json('skill_scores');
            $table->timestamp('completed_at');
            $table->unique(['student_id', 'assessment']);
        });

        Schema::create('course_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('certificate_code', 26)->unique();
            $table->string('recipient', 255);
            $table->string('school', 255);
            $table->string('issuer', 255);
            $table->string('title', 120);
            $table->text('message');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('issued_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_certificates');
        Schema::dropIfExists('course_assessment_results');
    }
};
