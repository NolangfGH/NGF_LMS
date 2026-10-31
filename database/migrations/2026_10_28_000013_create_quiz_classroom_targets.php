<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_teacher_quiz', function (Blueprint $table) {
            $table->foreignId('teacher_quiz_id')->constrained('teacher_quizzes')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->primary(['teacher_quiz_id', 'classroom_id']);
        });
        DB::table('teacher_quizzes')->orderBy('id')->chunkById(100, function ($quizzes) {
            foreach ($quizzes as $quiz) {
                DB::table('classroom_teacher_quiz')->insert(['teacher_quiz_id' => $quiz->id, 'classroom_id' => $quiz->classroom_id]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_teacher_quiz');
    }
};
