<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_key',80);
            $table->string('title');
            $table->text('code');
            $table->text('input')->nullable();
            $table->text('prediction')->nullable();
            $table->text('reflection')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('xp_earned')->default(0);
            $table->timestamps();
            $table->unique(['student_id','activity_key']);
        });
        Schema::create('lab_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('code_workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('title');
            $table->text('code');
            $table->text('input')->nullable();
            $table->text('prediction')->nullable();
            $table->text('reflection');
            $table->json('rubric')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedInteger('reward_budget')->default(0);
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['code_workspace_id','revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_submissions');
        Schema::dropIfExists('code_workspaces');
    }
};
