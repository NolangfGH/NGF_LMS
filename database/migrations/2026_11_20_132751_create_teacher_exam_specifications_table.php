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
        Schema::create('teacher_exam_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained('teacher_accounts')->cascadeOnDelete();
            $table->string('exam_key', 80);
            $table->json('rows');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['teacher_account_id', 'exam_key'], 'teacher_exam_spec_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_exam_specifications');
    }
};
