<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', fn (Blueprint $table) => $table->string('section', 80)->nullable());
        Schema::table('teacher_quizzes', function (Blueprint $table) {
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', fn (Blueprint $table) => $table->dropColumn('section'));
        Schema::table('teacher_quizzes', fn (Blueprint $table) => $table->dropColumn(['opens_at', 'closes_at']));
    }
};
