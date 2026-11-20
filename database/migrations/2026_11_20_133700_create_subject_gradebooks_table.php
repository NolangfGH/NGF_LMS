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
        Schema::create('subject_gradebooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_account_id')->constrained('teacher_accounts')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('course', 40);
            $table->string('school_year', 9);
            $table->string('term', 5);
            $table->json('definition');
            $table->json('entries');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['classroom_id', 'course', 'school_year', 'term'], 'subject_gradebook_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_gradebooks');
    }
};
