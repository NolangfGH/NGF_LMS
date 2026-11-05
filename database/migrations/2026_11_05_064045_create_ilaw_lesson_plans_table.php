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
        Schema::create('ilaw_lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained()->cascadeOnDelete();
            $table->string('lesson_key', 100);
            $table->json('content');
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 16)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('teacher_accounts')->nullOnDelete();
            $table->text('review_comment')->nullable();
            $table->timestamps();
            $table->unique(['teacher_account_id', 'lesson_key']);
            $table->index(['status', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ilaw_lesson_plans');
    }
};
