<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_quiz_id')->constrained()->cascadeOnDelete();
            $table->json('questions');
            $table->json('answers');
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->unique(['student_id', 'teacher_quiz_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('quiz_attempts'); }
};
