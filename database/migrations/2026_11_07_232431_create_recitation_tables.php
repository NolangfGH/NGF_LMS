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
        Schema::create('recitation_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('term', 10);
            $table->text('question');
            $table->json('accepted_answers');
            $table->boolean('case_sensitive')->default(true);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('recitation_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recitation_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('answer');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['recitation_round_id', 'student_id']);
        });
        Schema::create('recitation_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recitation_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_key');
            $table->text('answer');
            $table->boolean('correct');
            $table->timestamps();
            $table->unique(['recitation_round_id', 'student_id', 'request_key'], 'recitation_request_unique');
        });
        Schema::create('recitation_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recitation_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recitation_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->unsignedTinyInteger('score')->default(10);
            $table->unsignedTinyInteger('xp');
            $table->timestamps();
            $table->unique(['recitation_round_id', 'student_id']);
            $table->unique(['recitation_round_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recitation_awards');
        Schema::dropIfExists('recitation_attempts');
        Schema::dropIfExists('recitation_drafts');
        Schema::dropIfExists('recitation_rounds');
    }
};
