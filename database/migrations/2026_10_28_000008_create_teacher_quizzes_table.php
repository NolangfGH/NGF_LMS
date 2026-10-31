<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained('teacher_accounts')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->json('questions');
            $table->string('status')->default('draft');
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('time_limit')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_quizzes');
    }
};
