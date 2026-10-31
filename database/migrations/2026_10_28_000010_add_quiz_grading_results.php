<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_submissions', function (Blueprint $table) {
            $table->unsignedInteger('score')->nullable();
            $table->unsignedInteger('graded_questions')->default(0);
            $table->unsignedInteger('total_questions')->default(0);
            $table->json('grading_results')->nullable();
            $table->timestamp('graded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_submissions', function (Blueprint $table) {
            $table->dropColumn(['score', 'graded_questions', 'total_questions', 'grading_results', 'graded_at']);
        });
    }
};
